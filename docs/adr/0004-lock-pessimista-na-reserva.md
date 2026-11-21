# ADR 0004: Lock pessimista (SELECT ... FOR UPDATE) para vagas e dupla reserva

- Status: Aceito
- Data: 2026-11-21

## Contexto

Aulas populares lotam em segundos quando a grade abre. Duas pessoas disputando a última vaga não podem ser confirmadas ao mesmo tempo, e uma mesma pessoa clicando em duas aulas no mesmo horário não pode ficar com as duas. A fila de espera precisa respeitar a ordem de chegada e ser promovida no mesmo instante em que uma vaga é liberada.

## Decisão

Transação curta com locks pessimistas no MySQL (InnoDB), sempre na mesma ordem:

1. `users` do aluno `FOR UPDATE`: serializa as reservas do mesmo aluno, fechando a corrida de dupla reserva em aulas diferentes no mesmo horário.
2. `class_sessions` da aula `FOR UPDATE`: serializa a disputa pela vaga.
3. Leituras em `bookings` daquela aula também `FOR UPDATE` (contagem de confirmadas e reserva existente), para ler sempre o último commit, independentemente do snapshot do REPEATABLE READ.

O cancelamento trava a aula e depois as reservas (nunca o aluno), e promove o primeiro da fila (`queued_at` com microssegundos, desempate por `id`) na mesma transação. Ordem única de locks (aluno, aula, reservas) evita deadlock; mesmo assim `DB::transaction(..., 3)` repete em caso de deadlock.

Defesas complementares no banco: `UNIQUE (session_id, member_id)` impede duas linhas do mesmo aluno na mesma aula; `CHECK` de status e de capacidade positiva.

A prova é um teste com `pcntl_fork`: 8 processos, cada um com conexão própria, disparam ao mesmo tempo. Removendo os locks, o teste falha (deadlocks e reservas a mais).

## Consequências

- Correção simples de explicar e de testar, sem estado fora do banco.
- Reservas da mesma aula são serializadas: com transações de poucos milissegundos, centenas por segundo por aula, muito acima do pico de um estúdio.
- A linha do aluno vira ponto de serialização das reservas dele, o que é desejável.
- Depende de InnoDB e de nunca ler fora de transação na decisão. Está documentado no caso de uso.

## Alternativas consideradas

- **Constraint pura (tabela de assentos com `UNIQUE (session_id, seat_number)`)**: elegante para capacidade, mas a fila, a promoção e a dupla reserva entre aulas continuam precisando de coordenação. Mudar capacidade vira criar ou apagar assentos.
- **Contador com `UPDATE ... SET confirmed = confirmed + 1 WHERE confirmed < capacity`**: atômico e rápido, mas cria um valor denormalizado que pode divergir das reservas e não resolve a dupla reserva entre aulas.
- **Lock otimista (coluna `version`)**: bom com pouca disputa; na abertura da grade a disputa é alta e geraria muitas repetições.
- **Lock distribuído (Redis)**: mais uma peça para falhar, sem ganho num monólito com um banco.
- **SERIALIZABLE**: correto, mas transforma leituras em locks compartilhados em toda a transação e aumenta deadlocks.

## Adendo: edição de série e cancelamento de aula

Edição de série trava série -> aulas (por id) -> reservas; o cancelamento de aula pelo estúdio trava aula -> reservas (por id). Reservas e cancelamentos de reserva nunca travam a série, então os caminhos não formam ciclo com aluno -> aula -> reservas. A promoção da fila (`WaitlistPromoter`) roda sempre com a aula já travada.
