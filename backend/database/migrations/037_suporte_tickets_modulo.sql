-- Adiciona campo modulo na tabela de tickets (aparece quando categoria = 'problema')
ALTER TABLE `suporte_tickets` ADD COLUMN `modulo` varchar(100) DEFAULT NULL AFTER `categoria`;
