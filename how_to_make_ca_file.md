# SSL証明書の作成方法


## 自サーバー上で秘密鍵を作成する
```
# 秘密鍵の作成
$ openssl genrsa -des3 -out server_private.key 4096
```

## 次に証明書署名要求を作成する

```
# 証明書署名要求の作成
$ openssl req -new -key server_private.key -out server.csr
```


## プライベート認証局を作成する

```
# プライベート認証局の作成
$ openssl req -new -newkey rsa:4096 -nodes -out CA_CSR.csr -keyout CA_private_key.key
```
**上記コマンドで認証局用の秘密鍵と証明書署名要求を作成します。**

## 上記で作成した証明書署名要求を元に証明書を作成する

```
# 証明書の作成
$ openssl x509 -signkey CA_private_key.key \
    -days 3650 -req -in CA_CSR.csr \
    -out CA_certificate.pem
```

**上記コマンドで作成したCA_certificate.pemが証明書になります。**

## 作成したプライベート認証局の秘密鍵と証明書を元に、自サーバーの証明書を作成する

```
$ openssl x509 -req -days 3650 \
    -in CSR.csr -CA CA_certificate.pem \
    -CAkey CA_private_key.key -out certificate.pem \
     -set_serial 01 
```
