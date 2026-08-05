-- Adiciona 'link_externo' ao ENUM da coluna tipo em jornadas_modulos_videos (tenant).
-- Permite salvar conteúdo do tipo "Link externo" no bloco Conteúdo da jornada.
ALTER TABLE jornadas_modulos_videos
MODIFY COLUMN tipo ENUM('youtube','upload','link_externo') NOT NULL DEFAULT 'youtube';
