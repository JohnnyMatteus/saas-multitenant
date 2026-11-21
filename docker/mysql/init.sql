-- Banco separado para a suíte de testes (o PHPUnit apaga e recria as tabelas).
CREATE DATABASE IF NOT EXISTS studio_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON studio_test.* TO 'studio'@'%';
FLUSH PRIVILEGES;
