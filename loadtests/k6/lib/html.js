import { parseHTML } from 'k6/html';

export function extrairCsrf(html) {
  if (!html) {
    return '';
  }
  const doc = parseHTML(String(html));
  const valor = doc.find('input[name="_token"]').first().attr('value');
  if (valor) {
    return String(valor);
  }
  const json = String(html).match(/"csrf_token"\s*:\s*"([a-f0-9]{64})"/i);
  return json ? json[1] : '';
}

export function extrairIds(html, regex) {
  const ids = [];
  const re = regex.global ? regex : new RegExp(regex.source, regex.flags + 'g');
  let m;
  const texto = String(html || '');
  while ((m = re.exec(texto)) !== null) {
    const id = Number(m[1]);
    if (id && ids.indexOf(id) === -1) {
      ids.push(id);
    }
  }
  return ids;
}

export function primeiroMatch(html, regex) {
  const m = String(html || '').match(regex);
  return m ? m[1] : '';
}

export function formUrlEncoded(campos) {
  return Object.keys(campos)
    .map((chave) => encodeURIComponent(chave) + '=' + encodeURIComponent(String(campos[chave] ?? '')))
    .join('&');
}
