# Contrato proposto — API mobile v1

## Estratégia de compatibilidade

O backend já expõe `/api/auth/login` e `/api/parents/*`. Essas rotas não serão
removidas nem terão respostas alteradas. O app consumirá uma camada versionada
nova, que poderá reutilizar regras e consultas existentes.

Prefixo definido: `/api/v1`.

## Rotas

```text
POST   /auth/login
POST   /auth/refresh
POST   /auth/logout
GET    /parents/me
GET    /students
GET    /students/{student_id}/dashboard
GET    /students/{student_id}/exams
GET    /students/{student_id}/exams/{exam_id}
GET    /students/{student_id}/journeys
GET    /students/{student_id}/journeys/{journey_id}
GET    /students/{student_id}/writing-journeys
GET    /students/{student_id}/writing-journeys/{id}
GET    /students/{student_id}/lesson-plans
GET    /students/{student_id}/grades
GET    /students/{student_id}/report-card
GET    /students/{student_id}/school-messages
GET    /students/{student_id}/absences
GET    /notifications
PATCH  /notifications/{notification_id}/read
POST   /notifications/read-all
PUT    /devices/{device_id}
DELETE /devices/{device_id}
GET    /app/config
```

Listagens devem aceitar paginação e filtros. Datas são ISO-8601 com timezone.

## Respostas

Sucesso:

```json
{
  "data": {},
  "meta": { "request_id": "..." }
}
```

Erro:

```json
{
  "error": {
    "code": "STUDENT_ACCESS_DENIED",
    "message": "Você não tem permissão para acessar este aluno.",
    "request_id": "..."
  }
}
```

## Regras obrigatórias

- CPF é normalizado no servidor e nunca aparece em logs completos.
- Toda rota, exceto login, refresh e configuração pública, exige token.
- Toda consulta de aluno passa por uma única política de vínculo responsável–aluno.
- O token é vinculado ao tenant/escola e não pode atravessar tenants.
- Responsável inativo, senha alterada ou sessão revogada perde acesso.
- Tokens FCM pertencem a uma sessão/dispositivo e são removíveis.
- Segredos Firebase Admin existem somente no backend.

## Persistência aditiva

Criar migrations novas, sem modificar tabelas em produção de forma destrutiva:

- sessões/refresh tokens revogáveis;
- dispositivos mobile e tokens FCM;
- feed de notificações por destinatário e estado de leitura.
