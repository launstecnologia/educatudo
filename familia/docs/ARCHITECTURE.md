# Arquitetura do aplicativo

## Decisões

- Flutter com Android como primeira plataforma.
- Riverpod para estado e injeção de dependências.
- GoRouter para navegação e redirecionamento de sessão expirada.
- Dio para HTTP, com interceptors de autenticação, tenant e erros.
- `flutter_secure_storage` apenas para tokens; senha nunca é persistida.
- Firebase Messaging + notificações locais para push mobile.
- Ambientes `dev`, `staging` e `prod` via `--dart-define=APP_ENV=...`.
- Funcionalidades organizadas por feature para permitir entregas pequenas.

## Estrutura-alvo

```text
app/
├── android/
├── assets/
├── integration_test/
├── test/
├── lib/
│   ├── main.dart
│   ├── bootstrap.dart
│   ├── app/
│   │   ├── educatudo_app.dart
│   │   ├── router.dart
│   │   └── theme/
│   ├── core/
│   │   ├── auth/
│   │   ├── config/
│   │   ├── errors/
│   │   ├── network/
│   │   ├── push/
│   │   ├── storage/
│   │   └── widgets/
│   └── features/
│       ├── auth/
│       ├── students/
│       ├── home/
│       ├── exams/
│       ├── journeys/
│       ├── writing_journeys/
│       ├── lesson_plans/
│       ├── grades/
│       ├── messages/
│       ├── absences/
│       └── notifications/
└── pubspec.yaml
```

Cada feature terá, quando necessário, `data/`, `domain/` e `presentation/`.
Widgets não acessam HTTP diretamente; a sequência é página/controller → caso de
uso/repositório → cliente da API.

## Sessão e tenant

O login recebe CPF normalizado (11 dígitos), senha e o identificador seguro da
escola. O token deve conter o tenant e ser recusado se for usado em outra escola.
Access token e refresh token ficam no armazenamento seguro. Resposta `401`
encerra a sessão se a renovação não for possível.

## Push

O OneSignal web atual continua intacto. O aplicativo usa FCM em paralelo:

1. solicita permissão no momento contextual adequado;
2. obtém o token FCM;
3. registra o dispositivo depois do login;
4. atualiza o backend quando o FCM renovar o token;
5. desativa o dispositivo no logout;
6. abre uma rota interna a partir de payload tipado.

O push não deve incluir nota, falta ou outro dado escolar sensível na tela
bloqueada. Ele sinaliza que existe conteúdo novo; o detalhe é buscado pela API.
