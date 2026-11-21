## O que muda

<!-- Uma ou duas frases, em linguagem do domínio (estúdio, aula, reserva). -->

## Por quê

<!-- Issue relacionada, regra de negócio ou incidente. -->

## Como testar

- [ ] `make check` (Pint, PHPStan e PHPUnit com MySQL)
- [ ] Passos manuais, se houver:

## Checklist

- [ ] Toda consulta nova a dados de estúdio passa pelo global scope (sem `withoutTenancy()` sem justificativa)
- [ ] Teste de isolamento cobre o recurso novo (outro tenant recebe 404)
- [ ] Regras de agenda novas têm teste de borda (limite de horário, fuso, concorrência)
- [ ] Policy atualizada para papéis novos ou alterados
- [ ] ADR criado ou atualizado se a decisão for de arquitetura
- [ ] Docs (README, design doc, roteiros) atualizados
