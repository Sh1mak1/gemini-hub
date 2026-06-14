# AGENTS.md — gemini-hub

Cloud Agent / Cursor Agent 向けオンボーディング。本ファイルと `docs/DEPLOY.md` を最初に読むこと。

## プロジェクト概要

Slack や iOS Drafts からタスクを入力し、**Gemini API** で構造化して **PostgreSQL** に保存する**個人専用** ToDo システム。

| 項目 | 内容 |
|------|------|
| リポジトリ | `Sh1mak1/gemini-hub` |
| 本番 URL | https://gemini-hub.duckdns.org |
| 利用者 | 本人のみ（公開ユーザー登録は無効化済み） |

### 技術スタック

- **Backend:** Laravel 11（Breeze / Inertia）
- **Frontend:** React + Tailwind CSS + Vite
- **DB:** PostgreSQL 16
- **AI:** Gemini 2.5 Flash / Pro
- **外部連携:** Slack Events API、iOS Drafts HTTP API
- **キュー:** Laravel Queue（`database` ドライバ）
- **インフラ:** Docker Compose + 本番 Caddy（TLS）

---

## ディレクトリ構成

```
├── docker-compose.yml       # web / app / db / node
├── docker/                  # nginx, PHP Dockerfile, entrypoint
├── src/                     # Laravel アプリ本体（重要: ルートはここ）
├── scripts/deploy-production.sh
├── docs/DEPLOY.md           # デプロイ詳細
└── .github/workflows/deploy.yml
```

**重要:** `composer` / `artisan` / `npm` は **`src/` 配下**。実行は **Docker 内**（`docker compose exec app` / `docker compose run --rm node`）。ホスト PHP では動かない。

---

## 開発コマンド

```bash
docker compose up -d

# テスト（必須）
docker compose exec app php artisan test

# マイグレーション
docker compose exec app php artisan migrate

# フロントビルド
docker compose run --rm node sh -c "npm ci --legacy-peer-deps && npm run build"

# キュー（ローカル確認用・別ターミナル）
docker compose exec app php artisan queue:work
```

---

## デプロイ（Cloud Agent 向け）

**手動 SSH デプロイは不要。** `main` への push で GitHub Actions が本番反映する。

```
git commit → git push origin main → GitHub Actions → scripts/deploy-production.sh
```

- Workflow: `.github/workflows/deploy.yml`
- 詳細: `docs/DEPLOY.md`
- ヘルスチェック: https://gemini-hub.duckdns.org/up

### 完了条件（ユーザーが「デプロイして」と言った場合）

1. テストが通る
2. `main` に commit & push する
3. GitHub Actions の **Deploy to production** 成功を確認して報告する

本番反映内容（`scripts/deploy-production.sh` が変更種別に応じて実行）:

| 変更 | 自動実行 |
|------|----------|
| フロント（resources, vite, tailwind 等） | `npm ci && npm run build` |
| composer / PHP Dockerfile | `composer install` |
| docker-compose / docker/ | `docker compose up -d` |
| マイグレーション | `migrate --force` |
| config / routes / Providers | `optimize:clear` → cache 再生成 |
| PHP / config / routes | `docker compose restart app`（php-fpm） |
| nginx 設定 | `docker compose up -d web` |
| 毎回 | `config:cache`, `route:cache`, queue 再起動, `/up` 確認 |

---

## 主要ファイル

| 領域 | ファイル |
|------|----------|
| Task モデル | `src/app/Models/Task.php`, `src/app/Enums/TaskStatus.php`, `TaskCategory.php` |
| Slack | `src/app/Jobs/ProcessSlackEventJob.php`, `src/app/Http/Controllers/Api/SlackEventsController.php` |
| Gemini | `src/app/Services/Gemini/GeminiClient.php`, `TaskExtractionService.php` |
| Fallback | `src/app/Models/FallbackTask.php`, `TaskPersistenceService.php` |
| Drafts | `src/app/Http/Controllers/Api/DraftsController.php` |
| Web UI | `src/resources/js/Pages/Tasks/Index.jsx`, `TaskController.php` |
| デバッグ | `src/app/Http/Controllers/DebugController.php`, `OperationLogger.php` |
| 時刻表示 | `src/app/Support/DisplayTime.php`（UTC → JST） |

---

## API 早見表

| メソッド | URL | 認証 |
|---------|-----|------|
| POST | `/api/slack/events` | Slack 署名 |
| GET/POST/PUT | `/api/drafts/tasks` | Drafts Token |
| POST | `/api/drafts/tasks/add` | Drafts Token |
| GET | `/tasks` | Laravel セッション |
| PATCH | `/tasks/{task}/complete` | Laravel セッション |
| GET | `/debug/logs`, `/debug/database` | Laravel セッション |
| GET | `/up` | なし（ヘルスチェック） |

---

## 既知の落とし穴

| 問題 | 対処 |
|------|------|
| Gemini 404 | API URL は `generativelanguage.googleapis.com/v1beta`（`generativeai.googleapis.com` は不可） |
| migrate 失敗 | ホストではなく `docker compose exec app php artisan migrate` |
| `npm ci` 失敗 | `--legacy-peer-deps` を付ける |
| 日時が 9 時間ずれる | `DisplayTime` / `GEMINI_REFERENCE_TIMEZONE=Asia/Tokyo` を確認 |
| Gemini 503/429 | リトライ + `fallback_tasks` に保存（実装済み） |
| Slack 返信なし（other） | 投稿元チャンネルへフォールバック（実装済み） |
| storage 500 | `docker/php/entrypoint.sh` で権限修正（実装済み） |

---

## セキュリティ・禁止事項

- **個人専用アプリ** — 登録閉鎖・DB 非公開を維持すること
- `.env` / API キー / トークンを **コミット・出力しない**
- 以下は **ユーザーの明示的な指示がない限り実行しない**
  - `git push --force`, `git reset --hard`, `git clean -fd`
  - `migrate:fresh`, `migrate:reset`, `db:wipe`, `DROP`, `TRUNCATE`
  - 本番サーバーへの手動 SSH デプロイ（Actions が担当）
  - ファイル・ブランチの削除

---

## Cursor Cloud specific instructions

Cloud Agent は `.cursor/environment.json` で Docker Compose 環境を起動する。`install` 完了後に次で動作確認すること。

```bash
# サービス起動（install で up 済みのことが多い）
docker compose ps

# テスト（必須・完了条件）
docker compose exec app php artisan test

# フロント変更時
docker compose run --rm node sh -c "npm ci --legacy-peer-deps && npm run build"
```

- `composer` / `artisan` / `npm` は **Docker 内のみ**（`src/` がマウント先）
- Secrets は Cursor Dashboard に登録済み。`src/.env` を手動コミットしない
- Gemini / Slack の実 API テストは不要なら Unit テストのみでよい
- デプロイは `main` push → GitHub Actions（手動 SSH 不要）

環境定義: `.cursor/environment.json`, `.cursor/Dockerfile`, `.cursor/scripts/cloud-env-install.sh`

---

*最終更新: 2026-06-13*
