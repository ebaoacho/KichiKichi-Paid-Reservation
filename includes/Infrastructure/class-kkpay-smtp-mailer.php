<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 本番 SMTP メーラー設定。
 *
 * KKPAY_SMTP_HOST が .env または定数で設定されているときに phpmailer_init
 * フックで PHPMailer を SMTP モードに切り替える。
 * KKPAY_DEV_MAIL=true のときは DevMailer が優先されるため、このクラスは登録しない。
 *
 * 必須 .env 変数:
 *   KKPAY_SMTP_HOST       — SMTPサーバーホスト名
 *   KKPAY_SMTP_PORT       — SMTPポート番号（例: 587）
 *   KKPAY_SMTP_USER       — 認証ユーザー名（メールアドレス）
 *   KKPAY_SMTP_PASS       — 認証パスワード
 *   KKPAY_SMTP_ENCRYPTION — 暗号化方式（tls / ssl / 空文字）
 */
class KKPAY_Smtp_Mailer {

    public static function register() {
        add_action( 'phpmailer_init', array( __CLASS__, 'configure' ) );
    }

    /**
     * PHPMailer を .env の SMTP 設定に向ける。
     *
     * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public static function configure( $phpmailer ) {
        $host       = self::get_env( 'KKPAY_SMTP_HOST' );
        $port       = (int) self::get_env( 'KKPAY_SMTP_PORT' );
        $user       = self::get_env( 'KKPAY_SMTP_USER' );
        $pass       = self::get_env( 'KKPAY_SMTP_PASS' );
        $encryption = strtolower( trim( self::get_env( 'KKPAY_SMTP_ENCRYPTION' ) ) );

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = $port > 0 ? $port : 587;
        $phpmailer->SMTPAuth   = ( $user !== '' );
        $phpmailer->Username   = $user;
        $phpmailer->Password   = $pass;

        // 'ssl' → SMTPSecure::ENCRYPTION_SMTPS, 'tls' → STARTTLS, 空 → なし
        if ( $encryption === 'ssl' ) {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ( $encryption === 'tls' ) {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }
    }

    /**
     * SMTP_HOST が設定されているか（登録判定用）。
     */
    public static function is_configured() {
        return self::get_env( 'KKPAY_SMTP_HOST' ) !== '';
    }

    /**
     * 定数 → getenv → $_ENV → $_SERVER の順に環境変数を取得する。
     */
    private static function get_env( $name ) {
        if ( defined( $name ) ) {
            return trim( (string) constant( $name ) );
        }

        $value = getenv( $name );
        if ( $value === false && isset( $_ENV[ $name ] ) ) {
            $value = $_ENV[ $name ];
        }
        if ( $value === false && isset( $_SERVER[ $name ] ) ) {
            $value = $_SERVER[ $name ];
        }

        return $value === false ? '' : trim( (string) $value );
    }
}
