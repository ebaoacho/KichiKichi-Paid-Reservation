<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$legal_lang = sanitize_text_field( wp_unslash( $_GET['lang'] ?? 'en' ) );
$legal_lang = in_array( $legal_lang, array( 'en', 'ja', 'ko', 'zh-CN', 'zh-TW' ), true ) ? $legal_lang : 'en';

$languages = array(
    'en'    => 'English',
    'ja'    => '日本語',
    'ko'    => '한국어',
    'zh-CN' => '中文（简体）',
    'zh-TW' => '中文（繁體）',
);

$policies = array(
    'en' => array(
        'title'        => 'Policies',
        'intro'        => 'Before making a paid reservation, please review the cancellation policy, privacy policy, and terms of use below.',
        'cancellation' => array(
            'title' => 'Cancellation Policy',
            'body'  => array(
                'For Premium Reservations and Special Premium Reservations, the reservation fee is charged per seat at the time of payment.',
                'Premium Reservation fees are non-refundable after cancellation.',
                'For Special Premium Reservations only, if you cancel at least 3 days before your reservation date, the full reservation fee will be refunded through Stripe.',
                'For Special Premium Reservations cancelled within 3 days of the reservation date, the reservation fee is non-refundable.',
                'The reservation fee is for securing your seat and does not include food or drinks. Food and drinks must be paid for separately at the restaurant.',
                'Refunds are processed through Stripe. Depending on your card issuer or payment provider, it may take several business days for the refund to appear on your statement.',
            ),
        ),
        'privacy'      => array(
            'title' => 'Privacy Policy',
            'body'  => array(
                'We collect the information needed to manage reservations, including your name, email address, reservation date, time slot, number of seats, selected language, payment status, and cancellation status.',
                'Payment card details are processed by Stripe. We do not store your full card number or security code on this website.',
                'We use reservation information to process payments, confirm reservations, send reservation-related emails, handle cancellations and refunds, prevent duplicate bookings, and maintain operational records.',
                'We may share the minimum necessary information with service providers such as Stripe, email delivery providers, hosting providers, and WordPress administrators for reservation operation and security.',
                'Some technical information, such as IP address and user agent, may be stored as hashed values for security and audit purposes.',
                'If you need to correct or inquire about your reservation information, please contact the restaurant through the official contact channel.',
            ),
        ),
        'terms'        => array(
            'title' => 'Terms of Use',
            'body'  => array(
                'Reservations are accepted only when the requested date, time slot, seat type, and number of seats are available in the reservation system.',
                'Please enter accurate information. If your name or email address is incorrect, we may not be able to confirm or manage your reservation.',
                'Paid reservation fees are charged per seat and are handled according to the cancellation policy above.',
                'Food and drinks are not included in the reservation fee unless explicitly stated otherwise.',
                'We may cancel, refuse, or adjust a reservation if there is an obvious input error, duplicate booking, payment problem, system error, misuse, or operational need.',
                'Availability, reservation rules, prices, and policies may change when necessary for restaurant operations.',
            ),
        ),
    ),
    'ja' => array(
        'title'        => 'ポリシー',
        'intro'        => '有料予約をご利用になる前に、以下のキャンセルポリシー、プライバシーポリシー、利用規約をご確認ください。',
        'cancellation' => array(
            'title' => 'キャンセルポリシー',
            'body'  => array(
                'プレミアム予約およびスペシャルプレミアム予約では、お支払い時に席数ごとの予約料金をお支払いいただきます。',
                'プレミアム予約の予約料金は、キャンセル後も返金されません。',
                'スペシャルプレミアム予約のみ、予約日の3日前までにキャンセルされた場合、予約料金は Stripe を通じて全額返金されます。',
                'スペシャルプレミアム予約を予約日の3日前を過ぎてキャンセルされた場合、予約料金は返金されません。',
                '予約料金はお席を確保するための料金であり、お食事・お飲み物の代金は含まれていません。お食事・お飲み物は店舗にて別途お支払いください。',
                '返金は Stripe を通じて処理されます。カード会社または決済事業者によっては、明細に反映されるまで数営業日かかる場合があります。',
            ),
        ),
        'privacy'      => array(
            'title' => 'プライバシーポリシー',
            'body'  => array(
                '当店は、予約管理に必要な情報として、お名前、メールアドレス、予約日、時間帯、席数、選択言語、決済状況、キャンセル状況などを取得します。',
                'カード情報は Stripe により処理されます。当サイトでは、カード番号全体やセキュリティコードを保存しません。',
                '取得した予約情報は、決済処理、予約確認、予約に関するメール送信、キャンセル・返金対応、重複予約防止、運用記録のために利用します。',
                '予約運用と安全管理に必要な範囲で、Stripe、メール配信事業者、ホスティング事業者、WordPress 管理者などのサービス提供者に情報を共有する場合があります。',
                'IPアドレスやユーザーエージェントなどの技術情報は、セキュリティおよび監査目的でハッシュ化して保存する場合があります。',
                '予約情報の修正や確認が必要な場合は、店舗の公式連絡先までお問い合わせください。',
            ),
        ),
        'terms'        => array(
            'title' => '利用規約',
            'body'  => array(
                '予約は、予約システム上で希望日、時間帯、席種、席数に空きがある場合に限り受け付けます。',
                '正確な情報をご入力ください。お名前やメールアドレスに誤りがある場合、予約確認や予約管理ができないことがあります。',
                '有料予約の予約料金は席数ごとに発生し、上記キャンセルポリシーに従って取り扱います。',
                '明示されている場合を除き、予約料金にはお食事・お飲み物の代金は含まれません。',
                '明らかな入力ミス、重複予約、決済上の問題、システムエラー、不正利用、店舗運営上の必要がある場合、予約をキャンセル、拒否、または調整することがあります。',
                '空席状況、予約ルール、価格、各種ポリシーは、店舗運営上必要な場合に変更されることがあります。',
            ),
        ),
    ),
    'ko' => array(
        'title'        => '정책',
        'intro'        => '유료 예약을 진행하기 전에 아래의 취소 정책, 개인정보 처리방침 및 이용약관을 확인해 주세요.',
        'cancellation' => array(
            'title' => '취소 정책',
            'body'  => array(
                '프리미엄 예약 및 스페셜 프리미엄 예약은 결제 시 좌석 수에 따라 예약 수수료가 부과됩니다.',
                '프리미엄 예약 수수료는 취소 후에도 환불되지 않습니다.',
                '스페셜 프리미엄 예약에 한해 예약일 최소 3일 전까지 취소하는 경우 예약 수수료 전액이 Stripe를 통해 환불됩니다.',
                '스페셜 프리미엄 예약을 예약일로부터 3일 이내에 취소하는 경우 예약 수수료는 환불되지 않습니다.',
                '예약 수수료는 좌석 확보를 위한 비용이며 음식과 음료 비용은 포함되어 있지 않습니다. 음식과 음료는 매장에서 별도로 결제해야 합니다.',
                '환불은 Stripe를 통해 처리됩니다. 카드사 또는 결제 제공업체에 따라 명세서에 반영되기까지 영업일 기준 며칠이 걸릴 수 있습니다.',
            ),
        ),
        'privacy'      => array(
            'title' => '개인정보 처리방침',
            'body'  => array(
                '당사는 예약 관리를 위해 이름, 이메일 주소, 예약일, 시간대, 좌석 수, 선택 언어, 결제 상태, 취소 상태 등의 정보를 수집합니다.',
                '카드 정보는 Stripe에서 처리합니다. 당사는 이 웹사이트에 전체 카드 번호나 보안 코드를 저장하지 않습니다.',
                '예약 정보는 결제 처리, 예약 확인, 예약 관련 이메일 발송, 취소 및 환불 처리, 중복 예약 방지, 운영 기록 관리를 위해 사용됩니다.',
                '예약 운영 및 보안을 위해 필요한 최소한의 정보를 Stripe, 이메일 발송 서비스, 호스팅 제공업체, WordPress 관리자 등 서비스 제공자와 공유할 수 있습니다.',
                'IP 주소 및 사용자 에이전트와 같은 일부 기술 정보는 보안 및 감사 목적을 위해 해시 값으로 저장될 수 있습니다.',
                '예약 정보의 수정 또는 확인이 필요한 경우 공식 연락 창구를 통해 레스토랑에 문의해 주세요.',
            ),
        ),
        'terms'        => array(
            'title' => '이용약관',
            'body'  => array(
                '예약은 요청한 날짜, 시간대, 좌석 유형 및 좌석 수가 예약 시스템에서 이용 가능한 경우에만 접수됩니다.',
                '정확한 정보를 입력해 주세요. 이름이나 이메일 주소가 정확하지 않으면 예약 확인 또는 관리가 어려울 수 있습니다.',
                '유료 예약 수수료는 좌석당 부과되며 위의 취소 정책에 따라 처리됩니다.',
                '명시적으로 달리 안내되지 않는 한 예약 수수료에는 음식과 음료 비용이 포함되지 않습니다.',
                '명백한 입력 오류, 중복 예약, 결제 문제, 시스템 오류, 부정 이용 또는 운영상 필요가 있는 경우 예약을 취소, 거절 또는 조정할 수 있습니다.',
                '예약 가능 여부, 예약 규칙, 가격 및 정책은 레스토랑 운영상 필요한 경우 변경될 수 있습니다.',
            ),
        ),
    ),
    'zh-CN' => array(
        'title'        => '政策',
        'intro'        => '在进行付费预约前，请先阅读以下取消政策、隐私政策和使用条款。',
        'cancellation' => array(
            'title' => '取消政策',
            'body'  => array(
                '高级预约和特别高级预约将在付款时按席位收取预约费用。',
                '高级预约的预约费用在取消后不予退款。',
                '仅限特别高级预约，如果您在预约日期至少3天前取消，预约费用将通过 Stripe 全额退款。',
                '如果特别高级预约在预约日期3天内取消，预约费用不予退款。',
                '预约费用用于确保您的座位，不包含餐饮费用。餐饮费用需在店内另行支付。',
                '退款通过 Stripe 处理。根据发卡机构或支付服务商的不同，退款可能需要数个工作日才会显示在账单中。',
            ),
        ),
        'privacy'      => array(
            'title' => '隐私政策',
            'body'  => array(
                '我们会收集管理预约所需的信息，包括姓名、电子邮箱、预约日期、时间段、席位数、选择语言、付款状态和取消状态等。',
                '支付卡信息由 Stripe 处理。我们不会在本网站保存完整卡号或安全码。',
                '预约信息用于处理付款、确认预约、发送预约相关邮件、处理取消和退款、防止重复预约以及维护运营记录。',
                '为预约运营和安全管理所需，我们可能会与 Stripe、邮件发送服务商、托管服务商和 WordPress 管理员等服务提供商共享必要的最少信息。',
                'IP地址和用户代理等部分技术信息可能会以哈希值形式保存，用于安全和审计目的。',
                '如需更正或咨询预约信息，请通过官方联系方式联系餐厅。',
            ),
        ),
        'terms'        => array(
            'title' => '使用条款',
            'body'  => array(
                '仅当预约系统中所选日期、时间段、席位类型和席位数仍有空位时，预约才会被接受。',
                '请填写准确的信息。如果姓名或电子邮箱不正确，我们可能无法确认或管理您的预约。',
                '付费预约费用按席位收取，并按照上述取消政策处理。',
                '除非另有明确说明，预约费用不包含餐饮费用。',
                '如存在明显输入错误、重复预约、付款问题、系统错误、不当使用或运营需要，我们可能会取消、拒绝或调整预约。',
                '预约可用性、预约规则、价格和政策可能会因餐厅运营需要而变更。',
            ),
        ),
    ),
    'zh-TW' => array(
        'title'        => '政策',
        'intro'        => '在進行付費預約前，請先閱讀以下取消政策、隱私政策和使用條款。',
        'cancellation' => array(
            'title' => '取消政策',
            'body'  => array(
                '高級預約和特別高級預約會在付款時按席位收取預約費用。',
                '高級預約的預約費用在取消後不予退款。',
                '僅限特別高級預約，如果您在預約日期至少3天前取消，預約費用將透過 Stripe 全額退款。',
                '如果特別高級預約在預約日期3天內取消，預約費用不予退款。',
                '預約費用用於確保您的座位，不包含餐飲費用。餐飲費用需在店內另行支付。',
                '退款透過 Stripe 處理。根據發卡機構或支付服務商的不同，退款可能需要數個工作日才會顯示在帳單中。',
            ),
        ),
        'privacy'      => array(
            'title' => '隱私政策',
            'body'  => array(
                '我們會收集管理預約所需的資訊，包括姓名、電子郵件、預約日期、時間段、席位數、選擇語言、付款狀態和取消狀態等。',
                '支付卡資訊由 Stripe 處理。我們不會在本網站保存完整卡號或安全碼。',
                '預約資訊用於處理付款、確認預約、發送預約相關郵件、處理取消和退款、防止重複預約以及維護營運記錄。',
                '為預約營運和安全管理所需，我們可能會與 Stripe、郵件發送服務商、託管服務商和 WordPress 管理員等服務提供商共享必要的最少資訊。',
                'IP位址和使用者代理等部分技術資訊可能會以雜湊值形式保存，用於安全和稽核目的。',
                '如需更正或諮詢預約資訊，請透過官方聯絡方式聯絡餐廳。',
            ),
        ),
        'terms'        => array(
            'title' => '使用條款',
            'body'  => array(
                '僅當預約系統中所選日期、時間段、席位類型和席位數仍有空位時，預約才會被接受。',
                '請填寫準確的資訊。如果姓名或電子郵件不正確，我們可能無法確認或管理您的預約。',
                '付費預約費用按席位收取，並按照上述取消政策處理。',
                '除非另有明確說明，預約費用不包含餐飲費用。',
                '如存在明顯輸入錯誤、重複預約、付款問題、系統錯誤、不當使用或營運需要，我們可能會取消、拒絕或調整預約。',
                '預約可用性、預約規則、價格和政策可能會因餐廳營運需要而變更。',
            ),
        ),
    ),
);
?>

<div class="kkpay-legal-policies">
    <header class="kkpay-legal-policies__header">
        <p class="kkpay-legal-policies__eyebrow">KichiKichi Reservations</p>
        <h2 class="kkpay-legal-policies__title"><?php echo esc_html( $policies[ $legal_lang ]['title'] ); ?></h2>
        <p class="kkpay-legal-policies__intro"><?php echo esc_html( $policies[ $legal_lang ]['intro'] ); ?></p>
    </header>

    <div class="kkpay-legal-policies__copy" hidden>
        <?php foreach ( $policies as $code => $policy ) : ?>
            <span
                data-lang="<?php echo esc_attr( $code ); ?>"
                data-title="<?php echo esc_attr( $policy['title'] ); ?>"
                data-intro="<?php echo esc_attr( $policy['intro'] ); ?>"></span>
        <?php endforeach; ?>
    </div>

    <div class="kkpay-legal-policies__tabs" role="tablist" aria-label="Policy language">
        <?php foreach ( $languages as $code => $label ) : ?>
            <?php $selected = $code === $legal_lang; ?>
            <button
                type="button"
                class="kkpay-legal-policies__tab<?php echo $selected ? ' is-active' : ''; ?>"
                id="kkpay-legal-tab-<?php echo esc_attr( $code ); ?>"
                role="tab"
                aria-selected="<?php echo $selected ? 'true' : 'false'; ?>"
                aria-controls="kkpay-legal-panel-<?php echo esc_attr( $code ); ?>"
                data-lang="<?php echo esc_attr( $code ); ?>">
                <?php echo esc_html( $label ); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ( $policies as $code => $policy ) : ?>
        <?php $selected = $code === $legal_lang; ?>
        <div
            class="kkpay-legal-policies__panel<?php echo $selected ? ' is-active' : ''; ?>"
            id="kkpay-legal-panel-<?php echo esc_attr( $code ); ?>"
            role="tabpanel"
            aria-labelledby="kkpay-legal-tab-<?php echo esc_attr( $code ); ?>"
            <?php if ( ! $selected ) : ?>hidden<?php endif; ?>>

            <nav class="kkpay-legal-policies__nav" aria-label="<?php echo esc_attr( $policy['title'] ); ?>">
                <a href="#kkpay-cancellation-policy-<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $policy['cancellation']['title'] ); ?></a>
                <a href="#kkpay-privacy-policy-<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $policy['privacy']['title'] ); ?></a>
                <a href="#kkpay-terms-of-use-<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $policy['terms']['title'] ); ?></a>
            </nav>

            <?php foreach ( array( 'cancellation', 'privacy', 'terms' ) as $section ) : ?>
                <section id="kkpay-<?php echo esc_attr( $section === 'terms' ? 'terms-of-use' : $section . '-policy' ); ?>-<?php echo esc_attr( $code ); ?>" class="kkpay-legal-policies__section">
                    <article>
                        <h3><?php echo esc_html( $policy[ $section ]['title'] ); ?></h3>
                        <?php foreach ( $policy[ $section ]['body'] as $paragraph ) : ?>
                            <p><?php echo esc_html( $paragraph ); ?></p>
                        <?php endforeach; ?>
                    </article>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
