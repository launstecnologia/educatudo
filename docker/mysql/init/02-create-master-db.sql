-- Cria o banco master para multi-tenant (executado na primeira inicialização do MySQL no Docker).
CREATE DATABASE IF NOT EXISTS educatudo_master CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
