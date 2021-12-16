<?php

namespace myAmoCrmClass;

use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\CompaniesCollection;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Collections\NullTagsCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Filters\LeadsFilter;
use AmoCRM\Models\CompanyModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Models\NoteType\CommonNote;
use AmoCRM\Models\NoteType\ServiceMessageNote;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NullCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use League\OAuth2\Client\Token\AccessTokenInterface;
use AmoCRM\Models\CustomFieldsValues\SelectCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\SelectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\SelectCustomFieldValueModel;
use League\OAuth2\Client\Token\AccessToken;

use AmoCRM\Models\CustomFieldsValues\TextareaCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextareaCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextareaCustomFieldValueModel;

use AmoCRM\Client\AmoCRMApiClient;
use Symfony\Component\Dotenv\Dotenv;

use AmoCRM\OAuth\AmoCRMOAuth;

include_once __DIR__ . '/token_actions.php';

class newAmoCRM
{

    private $apiClient;
    private $pipelineId;

    public function __construct() {
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__ . '/.env.dist', __DIR__ . '/.env');

        $clientId = $_ENV['CLIENT_ID'];
        $clientSecret = $_ENV['CLIENT_SECRET'];
        $redirectUri = $_ENV['CLIENT_REDIRECT_URI'];
        $accessToken = newAmoCRM::getToken();
        $this->pipelineId = 4730488;
        
        $this->apiClient = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);
        $this->apiClient->setAccessToken($accessToken)
            ->setAccountBaseDomain($accessToken->getValues()['baseDomain'])
            ->onAccessTokenRefresh(
                function (AccessTokenInterface $accessToken, string $baseDomain) {
                    AddMessage2Log('Зашел в onAccessTokenRefresh');
                    $new_token = [
                        'accessToken' => $accessToken->getToken(),
                        'refreshToken' => $accessToken->getRefreshToken(),
                        'expires' => $accessToken->getExpires(),
                        'baseDomain' => $baseDomain,
                    ];
                    saveToken($new_token);
                }
            );
    }

    public static function getToken()
    {


        $accessToken = json_decode(file_get_contents(__DIR__ . '/tmp/token_info.json'), true);
        if (
            isset($accessToken)
            && isset($accessToken['accessToken'])
            && isset($accessToken['refreshToken'])
            && isset($accessToken['expires'])
            && isset($accessToken['baseDomain'])
        ) {
            $token = new AccessToken([
                'access_token' => $accessToken['accessToken'],
                'refresh_token' => $accessToken['refreshToken'],
                'expires' => $accessToken['expires'],
                'baseDomain' => $accessToken['baseDomain'],
            ]);
            return $token;
        } else {
            exit('Invalid access token ' . var_export($accessToken, true));
        }
    }

    public static function saveToken($accessToken)
    {
        AddMessage2Log('Зашел в saveToken');
        if (
            isset($accessToken)
            && isset($accessToken['accessToken'])
            && isset($accessToken['refreshToken'])
            && isset($accessToken['expires'])
            && isset($accessToken['baseDomain'])
        ) {
            $data = [
                'accessToken' => $accessToken['accessToken'],
                'expires' => $accessToken['expires'],
                'refreshToken' => $accessToken['refreshToken'],
                'baseDomain' => $accessToken['baseDomain'],
            ];
            file_put_contents(__DIR__ . '/tmp/token_info.json', json_encode($data));

        } else {
            AddMessage2Log('Invalid access token');
            exit('Invalid access token ' . var_export($accessToken, true));
        }
    }

    private function printError(AmoCRMApiException $e)
    {
        $errorTitle = $e->getTitle();
        $code = $e->getCode();
        $debugInfo = var_export($e->getLastRequestInfo(), true);

        $error = <<<EOF
Error: $errorTitle
Code: $code
Debug: $debugInfo
EOF;

        return '<pre>' . $error . '</pre>';
    }

    public function add_lead($lead_data) {

        $name = $lead_data['NAME'];
        $phone = $lead_data['PHONE'];
        $leadname = 'Заявка с сайта tarantasik.ru';
        //$content = 'test';
        if (isset($lead_data['559343']) && $lead_data['559343']) {
            $content = $lead_data['559343'];
        } else {
            $content = false;
        }
        AddMessage2Log('Дошел до создания контактов');
        $leadsService = $this->apiClient->leads();
        //Получим сделки и следующую страницу сделок
        try {
            $leadsCollection = $leadsService->get();
            //$leadsCollection = $leadsService->nextPage($leadsCollection);
        } catch (AmoCRMApiException $e) {
            AddMessage2Log(newAmoCRM::printError($e));
            die;
        }
        $contactID = $this->newContact($name, $phone);
        AddMessage2Log('Создал контакт');

        //Создадим сделку с заполненым бюджетом и привязанными контактами и компанией
        $lead = new LeadModel();
        $lead->setName($leadname)
            //->setPipelineId($this->pipelineId)
            ->setContacts(
                (new ContactsCollection())
                    ->add(
                        (new ContactModel())
                            ->setId($contactID)
                            ->setIsMain(true)
                    )
            );
        AddMessage2Log('Создал сделку');
        if ($content) {
            $customFields = new CustomFieldsValuesCollection();
            $contentField = new TextareaCustomFieldValuesModel();
            $contentField->setFieldId(559343);
            $contentField->setValues(
                (new TextareaCustomFieldValueCollection())
                    ->add((new TextareaCustomFieldValueModel())->setValue($content))
            );
            $customFields->add($contentField);
            $lead->setCustomFieldsValues($customFields);
        }

        $leadsCollection = new LeadsCollection();
        $leadsCollection->add($lead);
        try {
            $leadsCollection = $leadsService->add($leadsCollection);
        } catch (AmoCRMApiException $e) {
            newAmoCRM::printError($e);
            die;
        }

    }

    public function newContact($name, $phone) {
        $contact = new ContactModel();

        $contact->setName($name);
        $customFields = new CustomFieldsValuesCollection();
        $phoneField = (new MultitextCustomFieldValuesModel())->setFieldCode('PHONE');
        $phoneField->setValues(
            (new MultitextCustomFieldValueCollection())
                ->add(
                    (new MultitextCustomFieldValueModel())
                        ->setValue($phone)
                )
        );

        $customFields->add($phoneField);
        $contact->setCustomFieldsValues($customFields);
        try {
            $contactModel = $this->apiClient->contacts()->addOne($contact);
        } catch (AmoCRMApiException $e) {
            printError($e);
            die;
        }

        return $contactModel->getId();
    }
}
