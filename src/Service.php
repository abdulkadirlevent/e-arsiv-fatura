<?php

namespace Ethemkizil\EArsivFatura;
use Ethemkizil\EArsivFatura\Exceptions\UnexpectedValueException;
use Ramsey\Uuid\Uuid;

class Service
{

    /**
     * Api Urls
     */
    const BASE_URL = "https://earsivportal.efatura.gov.tr";
    const TEST_URL = "https://earsivportaltest.efatura.gov.tr";

    /**
     * Api Paths
     */
    const DISPATCH_PATH = "/earsiv-services/dispatch";
    const TOKEN_PATH = "/earsiv-services/assos-login";
    const REFERRER_PATH = "/intragiris.html";

    private $config = [
        "base_url"      => "https://earsivportaltest.efatura.gov.tr",
        "language"      => "tr",
        "currency"      => "TRY",
        "username"      => "",
        "password"      => "",
        "token"         => "",
        "service_type"  => "test",
    ];

    public $error = false;

    protected $curl_http_headers = [
        "accept: */*",
        "accept-language: tr,en-US;q=0.9,en;q=0.8",
        "cache-control: no-cache",
        "content-type: application/x-www-form-urlencoded;charset=UTF-8",
        "pragma: no-cache",
        "sec-fetch-mode: cors",
        "sec-fetch-site: same-origin",
        "connection: keep-alive"
    ];

    const COMMANDS = [
        "create_draft_invoice"                  => ["EARSIV_PORTAL_FATURA_OLUSTUR","RG_BASITFATURA"],
        "get_all_invoices_by_date_range"        => ["EARSIV_PORTAL_TASLAKLARI_GETIR", "RG_BASITTASLAKLAR"],
        "sign_draft_invoice"                    => ["EARSIV_PORTAL_FATURA_HSM_CIHAZI_ILE_IMZALA", "RG_BASITTASLAKLAR"],
        "get_invoice_html"                      => ["EARSIV_PORTAL_FATURA_GOSTER", "RG_BASITTASLAKLAR"],
        "cancel_draft_invoice"                  => ["EARSIV_PORTAL_FATURA_SIL", "RG_BASITTASLAKLAR"],
        "get_recipient_data_by_tax_id_or_tr_id" => ["SICIL_VEYA_MERNISTEN_BILGILERI_GETIR", "RG_BASITFATURA"],
        "send_sign_sms_code"                    => ["EARSIV_PORTAL_SMSSIFRE_GONDER", "RG_SMSONAY"],
        "verify_sms_code"                       => ["0lhozfib5410mp", "RG_SMSONAY"],
        "get_user_data"                         => ["EARSIV_PORTAL_KULLANICI_BILGILERI_GETIR", "RG_KULLANICI"],
        "update_user_data"                      => ["EARSIV_PORTAL_KULLANICI_BILGILERI_KAYDET", "RG_KULLANICI"]
    ];

    private $uuid;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->getToken();
    }

    public function setConfig($key, $val)
    {
        $this->config[$key] = $val;
        return $this->config[$key];
    }

    public function getConfig($key)
    {
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }

    public function setUuid($uuid)
    {
        if (!Uuid::isValid($uuid)) {
            throw new UnexpectedValueException("Belirttiğiniz uuid geçerli değil.");
        }
        $this->uuid = $uuid;
        return $uuid;
    }

    public function getUuid()
    {
        if (!isset($this->uuid)) {
            return Uuid::uuid1()->toString();
        }
        return $this->uuid;
    }

    public function currencyTransformerToWords($amount)
    {
        return "";
    }

    public function isError()
    {
        return $this->error;
    }

    public function getToken()
    {
        if (isset($this->config['token']) && !empty($this->config['token'])) {
            return $this->config['token'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->config['base_url']}/earsiv-services/assos-login");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->curl_http_headers);
        curl_setopt($ch, CURLOPT_REFERER, "{$this->config['base_url']}/intragiris.html");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            "assoscmd" => $this->config['service_type'] == 'prod' ? "anologin" : "login",
            "rtype" => "json",
            "userid" => $this->config['username'],
            "sifre" => $this->config['password'],
            "sifre2" => $this->config['password'],
            "parola" => 1,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_response = curl_exec($ch);
        $response = json_decode($server_response, true);
        curl_close($ch);


        if (isset($response["error"])) {
            $this->error = true;
            $result = $response;
        }else{
            $this->setConfig("token", $response['token']);
            $this->error = false;
            $result = $response['token'];
        }

        return $result;
    }

    public function runCommand($command, $page_name, $data = null, $url_encode = false)
    {
        $query = [
            "callid" => $this->getUuid(),
            "token" => $this->config['token'],
            "cmd" => $command,
            "pageName" => $page_name,
            "jp" => $url_encode ? urlencode(json_encode($data)) : json_encode($data),
        ];

        if(is_null($data)){
            $query["jp"] = "{}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->config['base_url']}/earsiv-services/dispatch");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->curl_http_headers);
        curl_setopt($ch, CURLOPT_REFERER, "{$this->config['base_url']}/login.jsp");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($server_response, true);

        if (isset($result["error"])) {
            $this->error = true;
        }else{
            $this->error = false;
        }
        return $result;
    }

    public function createDraftInvoice($invoice_details = [])
    {
        $invoice_data = [
            "faturaUuid" => $this->getUuid(),
            "faturaTarihi" => $invoice_details['date'],
            "saat" => $invoice_details['time'],
            "paraBirimi" => $this->config['currency'],
            "faturaTipi" => $invoice_details['faturaTipi'],
            "vknTckn" => $invoice_details['taxIDOrTRID'] ?? "11111111111",
            "aliciUnvan" => $invoice_details['title'] ?? "",
            "aliciAdi" => $invoice_details['name'],
            "aliciSoyadi" => $invoice_details['surname'],
            "vergiDairesi" => $invoice_details['taxOffice'],
            "bulvarcaddesokak" => $invoice_details['fullAddress'],
            "belgeNumarasi" => $invoice_details['belgeNumarasi'],
            "dovzTLkur" => $invoice_details['dovzTLkur'],
            "binaAdi" => $invoice_details['binaAdi'],
            "binaNo" => $invoice_details['binaNo'],
            "kapiNo" => $invoice_details['kapiNo'],
            "kasabaKoy" => $invoice_details['kasabaKoy'],
            "ulke" => $invoice_details['ulke'],
            "mahalleSemtIlce" => $invoice_details['mahalleSemtIlce'],
            "sehir" => $invoice_details['sehir'],
            "postaKodu" => $invoice_details['postaKodu'],
            "tel" => $invoice_details['tel'],
            "fax" => $invoice_details['fax'],
            "eposta" => $invoice_details['eposta'],
            "websitesi" => $invoice_details['websitesi'],
            "iadeTable" => $invoice_details['iadeTable'],
            "vergiCesidi" => $invoice_details['vergiCesidi'],
            "malHizmetTable" => $invoice_details['malHizmetTable'],
            "tip" => $invoice_details['tip'],
            "toplamIskonto" => $invoice_details['toplamIskonto'],
            "matrah" => (string) round($invoice_details['grandTotal'], 2),
            "malhizmetToplamTutari" => (string) round($invoice_details['grandTotal'], 2),
            "hesaplanankdv" => (string) round($invoice_details['totalVAT'], 2),
            "vergilerToplami" => (string) round($invoice_details['totalVAT'], 2),
            "vergilerDahilToplamTutar" => (string) round($invoice_details['grandTotalInclVAT'], 2),
            "odenecekTutar" => (string) round($invoice_details['paymentTotal'], 2),
            "not" => $invoice_details['not'],
            "siparisNumarasi" => $invoice_details['siparisNumarasi'],
            "siparisTarihi" => $invoice_details['siparisTarihi'],
            "irsaliyeNumarasi" => $invoice_details['irsaliyeNumarasi'],
            "irsaliyeTarihi" => $invoice_details['irsaliyeTarihi'],
            "fisNo" => $invoice_details['fisNo'],
            "fisTarihi" => $invoice_details['fisTarihi'],
            "fisSaati" => $invoice_details['fisSaati'],
            "fisTipi" => $invoice_details['fisTipi'],
            "zRaporNo" => $invoice_details['zRaporNo'],
            "okcSeriNo" => $invoice_details['okcSeriNo'],
            "hangiTip" => "5000/30000",
        ];

        foreach ($invoice_details['items'] as $item) {
            $invoice_data['malHizmetTable'][] = [
                "malHizmet" => $item['name'],
                "miktar" => $item['quantity'] ?? 1,
                "birim" => $item['unitType'],
                "birimFiyat" => (string) round($item['unitPrice'], 2),
                "fiyat" => (string) round($item['price'], 2),
                "hesaplananotvtevkifatakatkisi"=> $item['hesaplananotvtevkifatakatkisi'],
                "iskontoNedeni"=> $item['iskontoNedeni'],
                "iskontoOrani"=> $item['iskontoOrani'],
                "iskontoTutari"=> $item['iskontoTutari'],
                "malHizmetTutari" => (string) round(($item['quantity'] * $item['unitPrice']), 2),
                "kdvOrani" => (string) round($item['VATRate'], 0),
                "kdvTutari" => (string) round($item['VATAmount'], 2),
                "ozelMatrahTutari"=> $item['ozelMatrahTutari'],
                "vergininKdvTutari"=> $item['vergininKdvTutari'],
                "vergiOrani"=> (float) $item['vergiOrani']
            ];
        }

        $invoice = $this->runCommand(
            self::COMMANDS['create_draft_invoice'][0],
            self::COMMANDS['create_draft_invoice'][1],
            $invoice_data
        );
        if ($this->isError()){
            return $invoice;
        }else{
            return array_merge([
                "date" => $invoice_data['faturaTarihi'],
                "uuid" => $invoice_data['faturaUuid'],
            ], $invoice);
        }

    }

    public function getPhoneNumber()
    {
        $result = $this->runCommand(
            "EARSIV_PORTAL_TELEFONNO_SORGULA",
            "RG_BASITTASLAKLAR",
            null
        );
        return $result["data"]["telefon"];
    }

    public function getInvoiceFromAPI($ettn)
    {
        $data = [
            "ettn" => $ettn
        ];

        $result = $this->runCommand(
            "EARSIV_PORTAL_FATURA_GETIR",
            "RG_BASITFATURA",
            $data
        );
        return $result["data"];
    }

    public function getAllInvoicesByDateRange($start_date, $end_date)
    {
        $invoices = $this->runCommand(
            self::COMMANDS['get_all_invoices_by_date_range'][0],
            self::COMMANDS['get_all_invoices_by_date_range'][1],
            [
                "baslangic" => $start_date,
                "bitis" => $end_date,
                "hangiTip" => "5000/30000",
                "table" => []
            ]
        );
        return $invoices['data'];
    }

    public function findDraftInvoice($draft_invoice)
    {
        $drafts = $this->runCommand(
            self::COMMANDS['get_all_invoices_by_date_range'][0],
            self::COMMANDS['get_all_invoices_by_date_range'][1],
            [
                "baslangic" => $draft_invoice['date'],
                "bitis" => $draft_invoice['date'],
                "hangiTip" => "5000/30000",
            ]
        );

        foreach ($drafts['data'] as $item) {
            if ($item['ettn'] === $draft_invoice['uuid']) {
                return $item;
            }
        }

        return [];
    }

    public function signDraftInvoice($draft_invoice)
    {
        return $this->runCommand(
            self::COMMANDS['sign_draft_invoice'][0],
            self::COMMANDS['sign_draft_invoice'][1],
            [
                'imzalanacaklar' => [$draft_invoice]
            ]
        );
    }

    public function getInvoiceHTML($uuid, $signed = true)
    {
        $invoice = $this->runCommand(
            self::COMMANDS['get_invoice_html'][0],
            self::COMMANDS['get_invoice_html'][1],
            [
                'ettn' => $uuid,
                'onayDurumu' => $signed ? "Onaylandı" : "Onaylanmadı"
            ]
        );
        return $invoice['data'];
    }

    public function getDownloadURL($invoiceUUID, $signed = true)
    {
        $sign_status = urlencode($signed ? "Onaylandı" : "Onaylanmadı");

        return "{$this->config['base_url']}/earsiv-services/download?token={$this->config['token']}&ettn={$invoiceUUID}&belgeTip=FATURA&onayDurumu={$sign_status}&cmd=EARSIV_PORTAL_BELGE_INDIR";
    }

    public function createInvoice($invoice_details, $sign = true)
    {
        if (!isset($this->config['token']) || empty($this->config['token'])) {
            $this->getToken();
        }

        $draft_invoice = $this->createDraftInvoice($invoice_details);
        $draft_invoice_details = $this->findDraftInvoice($draft_invoice);

        if ($sign) {
            $this->signDraftInvoice($draft_invoice_details);
        }

        return [
            'uuid' => $draft_invoice['uuid'],
            'signed' => $sign
        ];
    }

    public function createInvoiceAndGetDownloadURL($args)
    {
        $invoice = $this->createInvoice($args['invoice_details'], false);
        return $this->getDownloadURL($invoice['uuid'], $invoice['signed']);
    }

    public function createInvoiceAndGetHTML($args)
    {
        $invoice = $this->createInvoice($args['invoice_details'], $args['sign'] ?? true);
        return $this->getInvoiceHTML($invoice['uuid'], $invoice['signed']);
    }

    public function cancelDraftInvoice($reason, $draft_invoice)
    {
        $cancel = $this->runCommand(
            self::COMMANDS['cancel_draft_invoice'][0],
            self::COMMANDS['cancel_draft_invoice'][1],
            [
                'silinecekler' => [$draft_invoice],
                'aciklama' => $reason
            ]
        );

        return $cancel['data'];
    }

    public function getRecipientDataByTaxIDOrTRID($tax_id_or_tr_id)
    {
        $recipient = $this->runCommand(
            self::COMMANDS['get_recipient_data_by_tax_id_or_tr_id'][0],
            self::COMMANDS['get_recipient_data_by_tax_id_or_tr_id'][1],
            [
                'vknTcknn' => $tax_id_or_tr_id
            ]
        );

        return $recipient['data'];
    }

    public function sendSignSMSCode($phone)
    {
        $sms = $this->runCommand(
            self::COMMANDS['send_sign_sms_code'][0],
            self::COMMANDS['send_sign_sms_code'][1],
            [
                "CEPTEL" => $phone,
                "KCEPTEL" => false,
                "TIP" => ""
            ]
        );

        return $sms["data"]['oid'];
    }

    public function verifySignSMSCode($sms_code, $operation_id, $invoices)
    {
        $data = [
            "SIFRE" => $sms_code,
            "OID" => $operation_id,
            'OPR' => 1,
            'DATA' => array($invoices)
        ];

        $result = $this->runCommand(
            self::COMMANDS['verify_sms_code'][0],
            self::COMMANDS['verify_sms_code'][1],
            $data
        );

        return $result;
    }

    public function getUserData()
    {
        $user = $this->runCommand(
            self::COMMANDS['get_user_data'][0],
            self::COMMANDS['get_user_data'][1],
            new \stdClass()
        );

        return [
            "taxIDOrTRID" => $user['data']['vknTckn'],
            "title" => $user['data']['unvan'],
            "name" => $user['data']['ad'],
            "surname" => $user['data']['soyad'],
            "registryNo" => $user['data']['sicilNo'],
            "mersisNo" => $user['data']['mersisNo'],
            "taxOffice" => $user['data']['vergiDairesi'],
            "fullAddress" => $user['data']['cadde'],
            "buildingName" => $user['data']['apartmanAdi'],
            "buildingNumber" => $user['data']['apartmanNo'],
            "doorNumber" => $user['data']['kapiNo'],
            "town" => $user['data']['kasaba'],
            "district" => $user['data']['ilce'],
            "city" => $user['data']['il'],
            "zipCode" => $user['data']['postaKodu'],
            "country" => $user['data']['ulke'],
            "phoneNumber" => $user['data']['telNo'],
            "faxNumber" => $user['data']['faksNo'],
            "email" => $user['data']['ePostaAdresi'],
            "webSite" => $user['data']['webSitesiAdresi'],
            "businessCenter" => $user['data']['isMerkezi']
        ];
    }

    public function updateUserData(array $user_data)
    {
        $fields = [
            "taxIDOrTRID" => 'vknTckn',
            "title" => 'unvan',
            "name" => 'ad',
            "surname" => 'soyad',
            "registryNo" => 'sicilNo',
            "mersisNo" => 'mersisNo',
            "taxOffice" => 'vergiDairesi',
            "fullAddress" => 'cadde',
            "buildingName" => 'apartmanAdi',
            "buildingNumber" => 'apartmanNo',
            "doorNumber" => 'kapiNo',
            "town" => 'kasaba',
            "district" => 'ilce',
            "city" => 'il',
            "zipCode" => 'postaKodu',
            "country" => 'ulke',
            "phoneNumber" => 'telNo',
            "faxNumber" => 'faksNo',
            "email" => 'ePostaAdresi',
            "webSite" => 'webSitesiAdresi',
            "businessCenter" => 'isMerkezi',
        ];

        $update_data = [];
        foreach ($fields as $source => $target) {
            if (isset($user_data[$source])) {
                $update_data[$target] = $user_data[$source];
            }
        }

        if (count($update_data) < 1) {
            return;
        }

        $user = $this->runCommand(
            self::COMMANDS['update_user_data'][0],
            self::COMMANDS['update_user_data'][1],
            $update_data
        );

        return $user['data'];
    }


    public function logOutFromAPI()
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->config['base_url']}".self::TOKEN_PATH);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->curl_http_headers);
        curl_setopt($ch, CURLOPT_REFERER, "{$this->config['base_url']}/index.jsp?token=".$this->config['token']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            "assoscmd" => "logout",
            "rtype" => "json",
            "token" => $this->config['token']
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_response = curl_exec($ch);
        curl_close($ch);

        return true;
    }
}