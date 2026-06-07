# デプロイ手順の内部解説（gemini-hub）

**目的:** 本番サーバーへ変更を反映するとき、各コマンドが**何をしているか**を学習用に整理したドキュメント。  
**対象読者:** リポジトリ所有者（個人運用）

> 機密情報（API キー、DB パスワード、トークン等）は**このドキュメントには書かない**。  
> 実際の値はサーバー上の `src/.env` とルート `.env` で管理する。

---

## 1. 全体像

### 本番の構成（ざっくり）

```
ブラウザ / Slack / Drafts
        ↓ HTTPS
    Caddy（ホスト OS 上）
    - ポート 443 で TLS 終端
    - Let's Encrypt で証明書自動取得
        ↓ HTTP（localhost:8080）
    Docker: nginx（web）
        ↓ FastCGI
    Docker: PHP-FPM（app）← Laravel アプリ本体（src/）
        ↓ SQL
    Docker: PostgreSQL（db）

    別プロセス: systemd（laravel-queue）
        ↓ docker compose exec
    同じ app コンテナ内で queue:work を常駐
```

| レイヤ | 役割 |
|--------|------|
| **Caddy** | インターネット向け HTTPS。Docker の外（ホスト）で動く |
| **web（nginx）** | 静的ファイル配信 + PHP へのリバースプロキシ |
| **app（php-fpm）** | Laravel の PHP 実行。`artisan` もここで動かす |
| **db（PostgreSQL）** | タスク・ユーザー・キュー用ジョブテーブル |
| **node** | **本番では常駐しない**。ビルド時だけ一時起動 |
| **laravel-queue** | Slack イベントなど非同期 Job の処理 |

### リポジトリとサーバーの関係

| 場所 | パス例 | 内容 |
|------|--------|------|
| GitHub | `Sh1mak1/gemini-hub` | ソースの正（`main` ブランチ） |
| 本番サーバー | `~/gemini-hub` | `git clone` した作業コピー |
| Laravel 本体 | `~/gemini-hub/src` | `composer.json` / `artisan` / React ソース |

本番では `src/` が Docker ボリュームで `app` / `web` にマウントされる。  
**コードを変えたら git pull でサーバー上のファイルが更新される**（イメージの再ビルドは通常不要）。

---

## 2. 典型的なデプロイの流れ

開発マシンでコミット・push したあと、本番でだいたい次の順番で実行する。

```bash
# --- 本番サーバーに SSH ログイン ---
ssh root@<サーバーIP>

cd ~/gemini-hub

# ① 最新コードを取得
git pull

# ② DB スキーマ変更があるときだけ
docker compose exec app php artisan migrate --force

# ③ フロント（React）を変更したとき
docker compose run --rm node sh -c "npm ci --legacy-peer-deps && npm run build"

# ④ Laravel 設定・ルートをキャッシュ
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache

# ⑤ キュー Worker のコードを読み直させる
systemctl restart laravel-queue
```

以下、**各ステップの内部で何が起きているか**を説明する。

---

## 3. ステップ別の内部解説

### ① `git pull`

| 項目 | 内容 |
|------|------|
| **やっていること** | GitHub の `main` とサーバーの作業ツリーを同期 |
| **更新されるもの** | PHP、React ソース、マイグレーション、設定ファイル例（`.env.example`）など |
| **更新されないもの** | `src/.env`（Git 管理外）、DB の中身、`storage/` のログ |

`.env` は pull では変わらない。新しい環境変数が増えたときは**手動で** `src/.env` に追記する。

---

### ② `php artisan migrate --force`

| 項目 | 内容 |
|------|------|
| **いつ必要か** | `database/migrations/` に新しいファイルが追加されたとき |
| **やっていること** | 未実行のマイグレーションを PostgreSQL に適用 |
| **`--force`** | 本番（`APP_ENV=production`）でも確認なしで実行するためのフラグ |

例: `fallback_tasks` テーブル追加、`tasks` カラム追加など。

**内部の流れ:**

1. `migrations` テーブルに「実行済み一覧」を保持
2. 未実行の PHP マイグレーションだけ `up()` を実行
3. DDL（`CREATE TABLE` 等）が DB に反映される

フロントだけ・PHP のロジックだけの変更なら **migrate は不要**。

---

### ③ `npm ci` + `npm run build`（node コンテナ）

| 項目 | 内容 |
|------|------|
| **いつ必要か** | `resources/js/` や `vite.config.js` などフロントを変更したとき |
| **なぜ node コンテナか** | 本番サーバーに Node を直接入れず、開発用と同じ環境でビルドするため |
| **`--rm`** | ビルド終了後にコンテナを削除（常駐させない） |
| **`--legacy-peer-deps`** | Vite 8 と React プラグインの peer 依存の競合を回避 |

**`npm ci` の内部:**

- `package-lock.json` に固定されたバージョンで `node_modules` をクリーンインストール
- `npm install` より再現性が高い（CI / 本番向き）

**`npm run build`（Vite）の内部:**

1. `resources/js/` の React（Inertia）をバンドル
2. Tailwind CSS を含むスタイルを処理
3. 成果物を `src/public/build/` に出力
   - `manifest.json` … ファイル名とハッシュの対応表
   - `assets/*.js`, `assets/*.css` … ブラウザが読み込む実体

**本番の web（nginx）は `public/` を document root にしている**ので、  
ビルドしないと新しい UI がブラウザに届かない（古い JS が残る）。

---

### ④ `config:cache` と `route:cache`

#### `php artisan config:cache`

| 項目 | 内容 |
|------|------|
| **やっていること** | `config/*.php` と `.env` を読み込み、1 つのキャッシュファイルにまとめる |
| **保存先** | `bootstrap/cache/config.php` |
| **効果** | リクエストごとの設定読み込みが減り、本番が速くなる |
| **注意** | `.env` を変えたあとは**必ず再実行**。古いキャッシュのまま動く |

`config/gemini.php` や `config/services.php` を変えたときも再キャッシュが必要。

#### `php artisan route:cache`

| 項目 | 内容 |
|------|------|
| **やっていること** | `routes/web.php` / `routes/api.php` などを解析し、ルート一覧をキャッシュ |
| **保存先** | `bootstrap/cache/routes-v7.php`（Laravel バージョンにより名前は異なる場合あり） |
| **効果** | ルート解決が高速化 |
| **注意** | ルート定義を変えたあとは再実行 |

**キャッシュしないもの:** `view:cache` は今回の運用では毎回は使っていない（Inertia + Vite 構成のため）。

---

### ⑤ `systemctl restart laravel-queue`

| 項目 | 内容 |
|------|------|
| **やっていること** | ホスト OS 上の systemd ユニット `laravel-queue.service` を再起動 |
| **なぜ必要か** | 長時間動く `queue:work` プロセスは、**起動時の PHP コードをメモリに保持**する。Job や Service を変えても再起動しないと古いコードのまま |

**laravel-queue の中身（概念）:**

```bash
docker compose exec -T app php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

| オプション | 意味 |
|------------|------|
| `queue:work` | `jobs` テーブルから Job を取り出して実行 |
| `--sleep=3` | キューが空なら 3 秒待って再ポーリング |
| `--tries=3` | 失敗時最大 3 回まで再試行 |
| `--timeout=90` | 1 Job の最大実行秒数 |

**このキューが処理する主な仕事:**

- Slack イベント受信後の `ProcessSlackEventJob`（Gemini 解析 → DB 保存 → Slack 返信）

Web リクエストは `php-fpm` が都度処理するが、Slack は 3 秒以内に 200 を返す必要があるため **Job に逃がしている**。

---

## 4. リクエストが通るまでの道のり（復習）

### ブラウザで `/tasks` を開いたとき

1. HTTPS で Caddy に到達
2. Caddy → `localhost:8080` の nginx
3. nginx → `public/index.php` → php-fpm（app）
4. Laravel がルート解決（キャッシュあればキャッシュから）
5. `TaskController` → Inertia が React 用 JSON + 初回 HTML を返す
6. ブラウザが `public/build/assets/*.js` を読み込み、React が描画

### Slack が `POST /api/slack/events` したとき

1. 同上で Laravel まで到達
2. 署名検証ミドルウェア
3. 即座に 200 OK（Slack タイムアウト対策）
4. `ProcessSlackEventJob` を `jobs` テーブルに enqueue
5. **別プロセス**の `queue:work` が Job を実行
6. Gemini API → DB → Slack `chat.postMessage`

---

## 5. Docker Compose の各サービス（詳細）

```yaml
# docker-compose.yml の要約
web:   nginx     → ポート ${APP_PORT}:80（本番は 8080）
app:   php-fpm   → artisan / composer は docker compose exec app で実行
db:    postgres  → ホストにはポート公開しない（セキュリティ）
node:  node:20   → 開発サーバー or 本番ビルド用（常駐不要）
```

### ボリュームマウント

```
./src  →  /var/www/html  （app / web / node 共通）
```

コード変更は pull だけでコンテナ内にも即反映。  
**例外:** `vendor/` はイメージビルド時やコンテナ内 `composer install` で揃える必要がある場合あり（本番運用では既にインストール済み想定）。

### entrypoint.sh（app コンテナ起動時）

```sh
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

Laravel がログやキャッシュを書けるよう権限を直す。  
権限不備だと **500 エラー**（特に `storage/logs`）になる。

---

## 6. 何を変えたときに何が必要か（早見表）

| 変更内容 | git pull | migrate | npm build | config:cache | route:cache | queue restart |
|----------|:--------:|:-------:|:---------:|:------------:|:-----------:|:-------------:|
| React / CSS | ✅ | — | ✅ | — | — | — |
| PHP ロジックのみ | ✅ | — | — | — | — | ✅（Job 関連なら必須） |
| `config/*.php` | ✅ | — | — | ✅ | — | — |
| `routes/*.php` | ✅ | — | — | — | ✅ | — |
| マイグレーション追加 | ✅ | ✅ | — | — | — | — |
| `.env` だけ変更 | — | — | — | ✅ | 場合により | ✅ |
| nginx 設定 | ✅ | — | — | — | — | `docker compose up -d web` |

---

## 7. デプロイ後の確認コマンド

```bash
# コンテナが生きているか
docker compose ps

# キュー Worker が動いているか
systemctl status laravel-queue

# HTTPS でアプリが応答するか
curl -s https://<本番ドメイン>/up

# 直近の操作ログ（UTC で書かれ、画面では JST 表示）
docker compose exec app tail -20 storage/logs/operations-$(date -u +%Y-%m-%d).log
```

ブラウザ確認:

- `/tasks` … UI・完了ボタン
- `/debug/logs` … 操作ログ（JST 表示）
- `/debug/database` … テーブル閲覧

---

## 8. よくあるつまずき（デプロイ周り）

| 現象 | よくある原因 | 対処の考え方 |
|------|-------------|-------------|
| UI が古いまま | `npm run build` 忘れ | node コンテナで再ビルド |
| 設定変更が効かない | `config:cache` が古い | 再キャッシュ or `config:clear` 後に再キャッシュ |
| 新ルートが 404 | `route:cache` が古い | `route:cache` 再実行 |
| Slack だけ動かない | queue worker 停止 or 古いコード | `systemctl restart laravel-queue` |
| 500 / ログに permission | `storage` 権限 | entrypoint / 手動 chown |
| migrate 失敗 | 本番 DB 状態と不整合 | ログ確認。`--force` は本番専用 |

---

## 9. ローカル開発との違い

| 項目 | ローカル | 本番 |
|------|----------|------|
| HTTPS | なし or ngrok | Caddy + Let's Encrypt |
| `APP_PORT` | 8000 など | 8080（Caddy 背後） |
| フロント | `npm run dev`（HMR） | `npm run build` の静的配信 |
| キュー | 手動で `queue:work` | systemd 常駐 |
| DB 公開 | Docker 内部のみ | 同左（5432 はホスト非公開） |

ローカルで `php artisan` を**ホスト**で実行すると `DB_HOST=db` が解決できず失敗することがある。  
**`docker compose exec app php artisan ...` を使う**のがこのプロジェクトの前提。

---

## 10. セキュリティ上の注意（デプロイ時）

- `.env` / `src/.env` は **Git に含めない**
- 本番の `APP_DEBUG=false` を維持
- PostgreSQL は Docker ネットワーク内のみ（`docker compose exec db psql ...` でアクセス）
- API キーやトークンをドキュメント・チャットに貼らない

---

## 11. 参考: 関連ファイル

| ファイル | デプロイとの関係 |
|----------|------------------|
| `docker-compose.yml` | サービス定義・ポート |
| `docker/nginx/default.conf` | `X-Forwarded-*`（HTTPS 認識） |
| `docker/php/entrypoint.sh` | storage 権限 |
| `src/.env.example` | 環境変数の一覧（値はダミー） |
| `src/bootstrap/cache/` | config / route キャッシュの出力先 |
| `src/public/build/` | Vite ビルド成果物 |

---

## 12. まとめ（一文で）

**デプロイ = サーバー上のコードを `git pull` で更新し、必要なら DB・フロント・Laravel キャッシュを整え、Slack 用のキュー Worker を再起動して、本番の「Web + 非同期処理」の両方が同じコードを見る状態にすること。**

---

*最終更新: 2026-06-08（gemini-hub 個人運用ベース）*
