## ◾️Docker移行時のディレクトリ構成

~~~~
ZenFlow/                  # プロジェクトルート
├── app/                  # 既存のLaravelアプリケーション
├── bootstrap/
├── config/
├── ...                   # 他の既存ディレクトリたち
├── Dockerfile            # メインのDockerfile
├── docker-compose.yml    # Dockerコンテナの定義
├── .dockerignore         # Dockerビルド時に無視するファイル
└── docker/               # Docker関連設定ファイル集約ディレクトリ
    ├── nginx/            # Nginx設定
    │   └── default.conf  # Nginxの設定ファイル
    ├── php/              # PHP設定
    │   ├── php.ini       # PHPのカスタム設定
    │   └── www.conf      # PHP-FPMの設定
    └── mysql/            # MySQL設定（必要な場合）
        └── my.cnf        # MySQLカスタム設定
~~~~

### ディレクトリ構成のポイント
1. 設定ファイルの配置場所について
   1. Dockerfileとdocker-compose.ymlはプロジェクトルートに置く
   2. 各サービス固有の設定はdocker/配下にまとめる
2. どこを分けてどこを集めるか
   1. 関連する設定がある場合はグループ化する
   2. サービス毎に分けると管理しやすい
3. ボリューム管理
   1. データの永続化にボリュームマウントが簡単 [ボリュームマウントについて](https://qiita.com/basabasa0129/items/7e1d590ac1d9a18e29fd)
   2. [ボリューム・マウントがしっくりきてない人用](https://qiita.com/Nats72/items/52d0dd14f7cedbb7b76f)
   3. 忙しい人用 : ボリュームマウントの存在意義:コンテナのデータ消失のリスクを回避するためにできた仕組み
   
4. git管理との統合
   1. Dockerの設定ファイルもgitで管理することができる
   2. dockerignoreで不要なファイルを除外
      ### ◾️dockerignore
        - まとめ
          - Docker版のgitignore
        - 詳細
          - ignoreの概念
            - ローカルにだけ置いておきたいファイル(設定ファイルだったり、ログファイルだったり、一時データだったり・・・)、gitでは大抵、**gitignore**にファイルを記載することでgitの管理対象から外される
          - gitから引っ張ってきたデータをDockerでそのままイメージを作ったら**gitigore**に記載したファイルがdocker環境では入ってしまったりする。
            - 理由としては、DockerはGitではなくファイルシステムの中身を見ているからです。**gitignore**は「記載されているファイルについてはバージョン管理を行わない」というだけです！　つまりファイルシステム自体には存在しています
           - dockerignoreってどんな時に使うのか、
             僕も「ローカル環境ならdockerを動かさないから開発用のメモや一時ファイルが入ったとしても問題ないんじゃね？」って思ってましたが、以下の理由から設定するメリットはあるようです。
                - 一時ファイルが増えすぎてイメージが大きくなった結果、ストレージが無駄に消費されることを避ける→ビルド時の遅延
                - チーム開発や本番移行時の事故を避ける　(これは多分ない)
                - 今後使うことがあったときの習慣化


### Dockerfileについて
実際にHerd(larvel専用のXamppみたいな環境)からDockerに移行してみます。
参考にしたのはlaradocを参考にdeepwikiに投げて、dockerfileを作成しました。　[laradocって何って人用のリンク](https://ramble.impl.co.jp/5722/)
ステップ毎に何をしているかをgeminiがまとめてくれました。
~~~~dockerfile
# STEP 1: ベースとなるPHPイメージを選ぶ
# Debian系のイメージなので apt-get を使う
FROM php:8.2-fpm

# STEP 2: OSレベルで必要なパッケージをインストールする
# nodejs/npm はフロントエンドビルドをコンテナ内で行うなら必要
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/* # キャッシュを削除してイメージサイズを小さくする

# STEP 3: PHPの拡張機能をインストールする
# Laravel 12の公式要件に合わせて、不足している拡張機能を追加しよう！
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    gd \
    zip \
    fileinfo \
    curl \
    dom \
    filter \
    hash \
    openssl \
    pcre \
    session \
    tokenizer \
    xml

# STEP 4: Composerをインストールする
# 最新のComposerイメージから実行ファイルだけをコピーする
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# STEP 5: 開発用ユーザーの作成 (セキュリティ対策)
# 通常のWebサーバーユーザーとして実行するためのユーザーを作成
RUN groupadd -g 1000 www && useradd -u 1000 -g www -m www -s /bin/bash

# STEP 6: 作業ディレクトリを設定し、ユーザーを切り替える
# Laravelプロジェクトの推奨ディレクトリは /var/www/html
WORKDIR /var/www/html

# STEP 7: Laravelプロジェクトのファイルをコンテナにコピー
# COPYの前にユーザーを切り替えることで、コピーされるファイルの所有者が最初からwwwになる
# 開発中は .env と vendor ディレクトリはGit管理しないので、ここではコピーしないか、.dockerignore で除外する
# あらかじめホスト側で composer install していれば vendor はコピーする必要がない
COPY --chown=www:www . /var/www/html

# STEP 8: ディレクトリのパーミッションを設定 (Laravelのstorageディレクトリなど)
# アプリケーションがファイルを書き込めるように、storageディレクトリの権限を調整
RUN chown -R www:www /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage \
    && chown -R www:www /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/bootstrap/cache

# STEP 9: ポートを公開 (PHP-FPMのデフォルトポート)
EXPOSE 9000

# STEP 10: コンテナ起動時に実行するコマンド
# PHP-FPMをフォアグラウンドで起動
CMD ["php-fpm"]
~~~~

### ◾️ Dockerfilr各行の意味
1. イメージタグを決める
~~~
FROM php8.2-fpm
~~~
php8.2のFPMのイメージをベースに構築しますって定義をここでしてます。
どんな基礎工事の上に家建てるかを決めてるイメージでいいです。(木材かコンクリか的な？)



### １についてのトピック　以下は本題からはずれてしまうので見なくてもいいです。(めっちゃ長くなります・・・)
docker-libraryをdeepwikiで読み込んでphpのタグについて調べてみました。
タグの構成要素↓
**phpバージョン×タイプ×ベースOS**

- 記載例１
  - php:8.4-cli
  - php:8.4-apache
  - php:8.4-fpm
- 記載例２
  - php8.4-cli-bookworm
  - php8.4-cli-bullseye
  - php8.4-cli-alpine3.21
### それぞれの記載例の違いがあるので注意が必要です
OSベースを記載しない場合とする場合では以下の挙動が違います！
ベースOSを記載しない場合、デフォルトのベースOSが選択されます
【例】
 - php:8.4-cli のみでdockerfileに記載をした場合、現在のデフォルトであるBookWormが勝手に選択されます。
- ベースOSを記載しない場合、タグを短く書くことができますし、ビルドするタイミングの最新の推奨OSが選択されることになります。
- デメリットとして、デフォルトOSが変更される場合があるので、ファイルパスがOS側の仕様が変更された影響で微妙に変わってエラーが起こったり、インストールコマンドがOS毎に違う場合があるため失敗したりすることがあります。
  - 例１. OSベースがDebian→Alpineに変わる
  - 例２. 使用しているバージョンが変わる　bullseye→bookwormに変わる
- 上記のことから個人開発以外ではOSまで明示的に記載するパターンが安定していて強いです。
### 備考 知見として
- 追加パッケージをインストールする予定、考慮する際は、ベースOSを明示してあげることが推奨されています。
先ほども書きましたが、OSの新リリース時にビルドが破綻するケースを避けるためです。
- DockerImageについては全然知識不足ですが以下の記事がわかりやすく説明されていました。
[DockerImageについて](https://zenn.dev/ken3pei/articles/1abbf7d974cf5d)


2. システムパッケージを準備する
~~~
# STEP 2: OSレベルで必要なパッケージをインストールする
# nodejs/npm はフロントエンドビルドをコンテナ内で行うなら必要
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/* # キャッシュを削除してイメージサイズを小さくする

# STEP 3: PHPの拡張機能をインストールする
# Laravel 12の公式要件に合わせて、不足している拡張機能を追加しよう！
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    gd \
    zip \
    fileinfo \
    curl \
    dom \
    filter \
    hash \
    openssl \
    pcre \
    session \
    tokenizer \
    xml

~~~

### 何をしているのか
~~~
①RUN apt-get update:
②&& apt-get install -y ...
~~~
①は 最新のソフトウェアを探してほしいことを OSに伝えてます。
②は①で更新したリストを全てインストールして！っていうコマンドです。今回なら以下が対象です
~~~
    git \
    curl \
    zip \
    unzip \
    nodejs \
    npm \
~~~
各モジュールの説明は省きます！(AIに投げて答えてもらってください！)
  -y については、実行中に途中で止まらないように全部「yes」で答えさせてます

~~~
&& rm -rf /var/lib/apt/lists/*:
~~~
Linuxコマンドを脳死で見なければわかります。
/var/lib/apt/lists/を　　先ほど集めた最新のモジュールリストに対して、rf(force:強制)とrm(remove:削除)してます。

~~~
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    gd \
    zip \
    fileinfo \
    curl \
    dom \
    filter \
    hash \
    openssl \
    pcre \
    session \
    tokenizer \
    xml
~~~
laravel仕様のモジュールをいれてます！(入れ方については先ほど説明したので割愛します)

docker-compose up -d 実行後

~~~
configure: error: Package requirements (oniguruma) were not met:
Package 'oniguruma', required by 'virtual:world', not found
~~~

"oniguruma"と言うライブラリが見つからないエラーが発生してました。
