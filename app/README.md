# EducaTudo Pais

Aplicativo Flutter, inicialmente Android, para pais e responsáveis acompanharem
a vida escolar dos alunos vinculados à sua conta.

## Estado atual

Projeto Android gerado com Flutter 3.44.2, Java 17 e Android SDK 36. A primeira
entrega implementa a fundação do aplicativo, autenticação por CPF e seleção de
aluno consumindo a API mobile versionada.

Não copie `google-services.json` para o repositório. Cada ambiente deve receber
esse arquivo por variável protegida no CI ou instalação local.

## Rodar o projeto

Requisitos:

- Flutter 3.44 ou compatível disponível no `PATH`;
- Android SDK 36;
- Java 17.

Na raiz do repositório:

```bash
cd app
flutter doctor
flutter pub get
flutter test
flutter run \
  --dart-define=APP_ENV=dev
```

Por padrão, o app usa a escola COLAG em `https://colag.educatudo.com`. Para
testar outro ambiente, informe `--dart-define=API_BASE_URL=https://...`.

## Gerar APK

```bash
flutter build apk --release \
  --dart-define=APP_ENV=prod \
  --dart-define=API_BASE_URL=https://sua-escola.exemplo
```

## Documentos

- [Arquitetura](docs/ARCHITECTURE.md)
- [Contrato da API mobile](docs/API_MOBILE_V1.md)
- [Plano de implementação segura](docs/IMPLEMENTATION_PLAN.md)
