<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ローカル開発用メーラー設定。
 * KKPAY_DEV_MAIL=true のときだけ phpmailer_init フックで Mercury SMTP に差し替える。
 * 本番環境では絶対にロードしないこと。
 */
class KKPAY_Dev_Mailer {

    // Mercury のデフォルト SMTP ポート
    const MERCURY_HOST = '127.0.0.1';
    const MERCURY_PORT = 25;

    public static function register() {
        add_action( 'phpmailer_init', array( __CLASS__, 'configure' ) );
    }

    /** PHPMailer を Mercury SMTP に向ける */
    public static function configure( $phpmailer ) {
        $phpmailer->isSMTP();
        $phpmailer->Host       = self::MERCURY_HOST;
        $phpmailer->Port       = self::MERCURY_PORT;
        $phpmailer->SMTPAuth   = false;
        $phpmailer->SMTPSecure = '';
    }

    /** KKPAY_DEV_MAIL 環境変数 / 定数が truthy かどうかを判定する */
    public static function is_enabled() {
        // wp-config.php の定数を最優先
        if ( defined( 'KKPAY_DEV_MAIL' ) ) {
            return filter_var( constant( 'KKPAY_DEV_MAIL' ), FILTER_VALIDATE_BOOLEAN );
        }

        // .env / $_ENV / $_SERVER からの読み取り（KKPAY_Env_Loader が先にロード済み）
        $value = getenv( 'KKPAY_DEV_MAIL' );
        if ( $value === false && isset( $_ENV['KKPAY_DEV_MAIL'] ) ) {
            $value = $_ENV['KKPAY_DEV_MAIL'];
        }
        if ( $value === false && isset( $_SERVER['KKPAY_DEV_MAIL'] ) ) {
            $value = $_SERVER['KKPAY_DEV_MAIL'];
        }

        if ( $value === false ) {
            return false;
        }

        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }
}
