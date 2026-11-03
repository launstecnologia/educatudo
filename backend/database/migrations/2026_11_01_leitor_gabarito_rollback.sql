-- Desfaz 2026_11_01_leitor_gabarito.sql
-- Apaga as folhas e as leituras do leitor. Não mexe nas notas já lançadas em provas_respostas.

DROP TABLE IF EXISTS `leitor_gabarito_leituras`;
DROP TABLE IF EXISTS `leitor_gabarito_folhas`;
