-- Rollback: 2026_08_18_componentes_curriculares_seed_catalogo.sql
--
-- Sem faixa de id reservada nesta versão (ver comentário no arquivo principal
-- sobre por que IDs negativos/faixa alta foram descartados) — a remoção
-- identifica as linhas do catálogo pelo par exato (nome, código) usado no
-- INSERT. Isso pode remover, por coincidência, um componente que a escola
-- tenha cadastrado manualmente com o mesmo nome E mesmo código (baixo risco,
-- mas existe). Componentes com o mesmo nome e código diferente (ou vice-versa)
-- não são afetados.

DELETE FROM materias WHERE
  (nome = 'Língua Portuguesa' AND codigo = 'LPO') OR
  (nome = 'Matemática' AND codigo = 'MAT') OR
  (nome = 'Ciências' AND codigo = 'CIE') OR
  (nome = 'História' AND codigo = 'HIS') OR
  (nome = 'Geografia' AND codigo = 'GEO') OR
  (nome = 'Arte' AND codigo = 'ART') OR
  (nome = 'Educação Física' AND codigo = 'EDF') OR
  (nome = 'Língua Inglesa' AND codigo = 'ING') OR
  (nome = 'Ensino Religioso' AND codigo = 'ER') OR
  (nome = 'Biologia' AND codigo = 'BIO') OR
  (nome = 'Física' AND codigo = 'FIS') OR
  (nome = 'Química' AND codigo = 'QUI') OR
  (nome = 'Filosofia' AND codigo = 'FIL') OR
  (nome = 'Sociologia' AND codigo = 'SOC') OR
  (nome = 'Língua Espanhola' AND codigo = 'ESP') OR
  (nome = 'Redação' AND codigo = 'RED') OR
  (nome = 'Literatura' AND codigo = 'LIT') OR
  (nome = 'Libras' AND codigo = 'LIB') OR
  (nome = 'Projeto de Vida' AND codigo = 'PVI') OR
  (nome = 'Educação Financeira' AND codigo = 'EDFN') OR
  (nome = 'Tecnologia e Inovação' AND codigo = 'TEC') OR
  (nome = 'Computação' AND codigo = 'CMP') OR
  (nome = 'Informática' AND codigo = 'INF') OR
  (nome = 'Robótica' AND codigo = 'ROB') OR
  (nome = 'Programação' AND codigo = 'PROG') OR
  (nome = 'Empreendedorismo' AND codigo = 'EMP') OR
  (nome = 'Música' AND codigo = 'MUS') OR
  (nome = 'Teatro' AND codigo = 'TEA') OR
  (nome = 'Dança' AND codigo = 'DAN') OR
  (nome = 'Xadrez' AND codigo = 'XAD') OR
  (nome = 'Educação Ambiental' AND codigo = 'ECO') OR
  (nome = 'Educação para Cidadania' AND codigo = 'CID');
