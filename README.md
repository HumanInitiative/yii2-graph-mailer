# Yii2 Graph Mailer

Ini adalah komponen untuk mengirim email menggunakan Microsoft Graph API [sendMail](https://learn.microsoft.com/en-us/graph/api/user-sendmail?view=graph-rest-1.0&tabs=http).

## Instalasi

### 1. Install via Composer

```bash
composer require humaninitiative/yii2-graph-mailer:"dev-master"
```

### 2. Register App

Untuk mendapatkan credential OAuth2 (Client ID dan Client Secret).

1. Login ke [Portal Azure ](https://portal.azure.com) atau [Entra Admin](https://entra.microsoft.com)
2. Pilih **App registrations** >  **New registration**
3. Isi form:
   * **Name** : Contoh "Yii2 Send Mailer"
   * **Supported account types** : Pilih "Accounts in this organizational directory only" untuk aplikasi internal
4. Klik **Register**
5. Simpan **Application (client) ID** dan **Directory (tenant) ID**
6. Buka tab **Certificates & secrets**
7. Klik **New client secret**
   * Isi deskripsi, misalnya "secret mail"
   * Pilih durasi expires (misalnya 12 months)
   * Klik **Add**
8. Copy **Value** dari client secret tersebut. Ini hanya ditampilkan sekali.
9. Buka tab  **API permissions**
   * Klik **Add a permission**.
   * Pilih **Microsoft Graph**
   * Pilih **Application permissions**
   * Cari dan centang **`Mail.Send`**
   * Klik **Add permissions**
   * **Jangan lupa** Klik tombol **Grant admin consent** dan konfirmasi

### 3. Config Aplikasi

Tambahkan pada `.env` :

```
OUTLOOK_TENANT_ID="TENANT_ID"
OUTLOOK_CLIENT_ID="CLIENT_ID"
OUTLOOK_CLIENT_SECRET="CLIENT_SECRET"
OUTLOOK_EMAIL="FROM_EMAIL"
```

Lalu tambahkan pada `config/web.php` :

```
'components' => [
	'graphMailer' => [
		'class' => 'humaninitiative\graph\mailer\GraphMailer',
            	'tenantId' => $_ENV['OUTLOOK_TENANT_ID'],
            	'clientId' => $_ENV['OUTLOOK_CLIENT_ID'],
            	'clientSecret' => $_ENV['OUTLOOK_CLIENT_SECRET'],
            	'email' => $_ENV['OUTLOOK_EMAIL'],
	],
],
// Jika ingin menggunakan API untuk digunakan aplikasi lain
'as access' => [
	'allowActions' => [
		'email/send',
	],
],
'controllerMap' => [
	'email' => [
		'class' => 'humaninitiative\graph\mailer\controllers\EmailController',
	],
],
```

### 4. Contoh Penggunaan

```
Yii::$app->graphMailer->compose()
	->setFrom($_ENV['OUTLOOK_EMAIL'])
        ->setTo('test@test.com')
        ->setSubject('test')
        ->setHtmlBody('<p>test pengiriman email via graph api</p>')
	->setCc('cc@test.com')
        ->setReplyTo('replyTo@test.com')
        ->attach($file->tempName, ['fileName' => $file->name])
        ->send();
```

atau compose dari file html

```
Yii::$app->graphMailer->compose('file-html', ['model'=>$model])
	->setFrom($_ENV['OUTLOOK_EMAIL'])
        ->setTo('test@test.com')
        ->setSubject('test')
	->setCc('cc@test.com')
        ->setReplyTo('replyTo@test.com')
        ->attach($file->tempName, ['fileName' => $file->name])
        ->send();

```
