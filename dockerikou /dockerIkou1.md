### 🐳Herd から　Laravel + Dockerへお引越し！！　爆速ローカル開発環境を構築するまでの前手順

##  Herd -> Dockerへ移行した理由
### 1. sqliteのデータが消えてしまう問題
先日、原因不明のトラブルによりバックアップが取得できず、さらにデータが欠損するという不具合に遭遇しました。
SQLiteデータベースの破損を修復し、削除されたデータを可能な限り取り戻す方法を探してきて直す方法をやってもいいんですが、僕の取り扱いが良くなかったのか今まで２回くらい似たようなことが行ったのが１つ目の理由です。

### 2. チームでsvn環境からの脱却　+ dockerでローカル開発環境　を始める話が出た。
今まで会社ではsvn環境でバージョン管理を行なっていたこともあって、チームのローカル環境はみんなで共有してました。
dockerでローカルサーバーを立てて開発をおこなるフローを今までやったことがなかったこともあり、実際にやってみようと思ったことがdocker移行を行う２つ目の理由です

## 完成イメージ
- 完成イメージは今までHerdで開発していたものをdockerでホストを立てて開発できるようになること
- sqlite->phpmyadminで運用が行えること

では実際に行ったフローを記載していきます！

## 1. プロジェクト構成
プロジェクト構成は以下になります。
構成は一般的なもの(インターネッツに置いてあるもの)に沿って作成を進めました。
プロジェクトを作成する際は以下の要点を押さえましょー
- 設定系は **docker/** に集約させること
- ルート直下に　**Dockerfile / docker-compose.yml**　を置くこと

| 役者 | 何をする？ | イメージ |
|------|-----------|----------|
| app | PHP(Laravel) を実行 | `php:8.2-fpm` を自作ビルド |
| nginx | リバプロ + 静的配信 | `nginx:1.25-alpine` |
| db | MySQL 8 | `mysql:8.0` |
| phpmyadmin | GUI DB クライアント | `phpmyadmin/phpmyadmin` |

~~~
ZenFlow/
├── Dockerfile
├── docker-compose.yml
├── docker/
│   ├── nginx/default.conf
│   └── php/php.ini
└── (Laravel の既存ディレクトリ)
~~~

## Dockerfile

Dockerfileは　**コンテナ作成を行う手順書**みたいなものです


どんな材料を使っているのか
準備を行う手順
どうやって作っていくのか
どうやって表示・起動するのか

みたいな手順方法をまとめておいてビルドする際に注文します。
Dokerfileを作成する際に調べてみたのですが、詳細を説明するには広すぎる&深すぎてここで書く内容ではないので別でまとめて共有できるようにします！

一旦僕は以下の構成内容でビルドすることにしました。
備考 : 使用しているのがMacの人は注意です。ARM Mac(CPUがappleシリコンのやつ) ではliboing-dev がないとmbstring がこける罠が存在します！！

~~~
FROM php:8.2-fpm

# --- パッケージ ---
RUN apt-get update && apt-get install -y \
    git curl zip unzip nodejs npm \
    libonig-dev libpng-dev libxml2-dev libzip-dev \
 && rm -rf /var/lib/apt/lists/*

# --- PHP 拡張 ---
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl gd zip

# --- Composer ---
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# --- ユーザー／権限 ---
RUN groupadd -g 1000 www && useradd -u 1000 -g www -m www
WORKDIR /var/www
COPY --chown=www:www . /var/www
RUN chown -R www:www storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
~~~

## docker-compose.yml
### docker-compose.yml ってなあに？なんで必要なの？
**複数のDockerコンテナをまとめて管理するためのファイル** です！
さっきDockerfileを作りましたよね。Dockerfileは一つのコンテナの設計書って感じなんです。
今回の場合だと、Laravelアプリケーション本体を動かすコンテナ(PHPとweb鯖)とデータベース(mysql),Redisだったりキャッシュとかキューを処理するコンテナが最低限必要になります。
上記のコンテナを個別に処理したり連携したりするのが**docker-compose**の役割ってことになります
一つ一つのコンテナを手動で起動したりすることはできるみたいなんですがめんどいんで絶対composeを使ったほうがいいですよね

### YAML の３つのブロック
1. **services:**    => コンテナを定義するやつ
2. **networks:**　  => 相互通信させる橋
3. **volumes:**     => データ永続化 

- services
コンテナサービスを定義する場所です！
サービスが何で動いているのかがここで分かるっていうのとdocker環境を統一させるためにこの設定があります！
- networks
コンテナ同士が相互に通信するための橋を設定します
Dockerは各コンテナで離れたコンテナを隔離された空間で動かすので設定をしないとアプリのコンテナはデータベースの

~~~
services:
  app:
    build: .
    volumes:
      - .:/var/www
      - ./docker/php/php.ini:/usr/local/etc/php/conf.d/local.ini
    networks: [zenflow]

  nginx:
    image: nginx:1.25-alpine
    ports: ['8000:80']
    volumes:
      - .:/var/www
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on: [app]
    networks: [zenflow]

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: zenflow
      MYSQL_ROOT_PASSWORD: root
      MYSQL_USER: zenflow
      MYSQL_PASSWORD: password
    ports: ['3306:3306']
    volumes: [dbdata:/var/lib/mysql]
    networks: [zenflow]

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    ports: ['8080:80']
    environment:
      PMA_HOST: db
      PMA_PORT: 3306
      MYSQL_ROOT_PASSWORD: root
    depends_on: [db]
    networks: [zenflow]

networks: {zenflow: {driver: bridge}}
volumes: {dbdata:}
~~~