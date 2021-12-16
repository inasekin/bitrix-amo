<?
require_once $_SERVER['DOCUMENT_ROOT'] . '/amo/create_lead.php';

use myAmoCrmClass\newAmoCRM;

use \Bitrix\Main\EventManager;

//Обработка и отправка нового элеммента инфоблока на почту
AddEventHandler("iblock", "OnAfterIBlockElementAdd", array("AfterElementAdd", "AfterElementAddSendMail"));

class AfterElementAdd
{
    function AfterElementAddSendMail(&$arFields)
    {
//        //Проверили номер инфоблока
//        if ($arFields["IBLOCK_ID"] == 70) {
//            $res = CIBlockElement::GetByID($arFields['ID']);
//            $ar_res = $res->GetNext();
//
//            //Выборка свойств
//            $arEventFields = array(
//                "AUTHOR_NAME" => $ar_res["NAME"],
//                "AUTHOR_PHONE" => $arFields["PROPERTY_VALUES"][289],
//            );
//            //Отправили нужное письмо с вышеуказанными данными отправить('почтовый шаблон', 'id сайта', 'выборка свойств') -
//            // по умолчанию отправляет на email указанный в админке
//            CEvent::Send("RS_FLYAWAY_CONTACTS", SITE_ID, $arEventFields);
//        }
        if ($arFields["IBLOCK_ID"] == 71) {
            $res = CIBlockElement::GetByID($arFields['ID']);
            $ar_res = $res->GetNext();


            //Выборка свойств
            $arEventFields = array(
                "AUTHOR_NAME" => $ar_res["NAME"],
                "AUTHOR_PHONE" => $arFields["PROPERTY_VALUES"][290],
            );
            //Отправили нужное письмо с вышеуказанными данными отправить('почтовый шаблон', 'id сайта', 'выборка свойств') -
            // по умолчанию отправляет на email указанный в админке
            CEvent::Send("RS_FLYAWAY_CONTACTS", SITE_ID, $arEventFields);
            // Сохраним в лог сообщение
            AddMessage2Log("Отправлено сообщение заявка с сайта", "amo");
        }

        if ($arFields["IBLOCK_ID"] == 72) {
            $res = CIBlockElement::GetByID($arFields['ID']);
            $ar_res = $res->GetNext();

            //Выборка свойств
            $arEventFields = array(
                "AUTHOR_NAME" => $ar_res["NAME"],
                "AUTHOR_PHONE" => $arFields["PROPERTY_VALUES"][291],
                "COMMENT" => $arFields["PROPERTY_VALUES"][293],
                "EXT_FIELD_0" => $arFields["PROPERTY_VALUES"][292],
            );
            //Отправили нужное письмо с вышеуказанными данными отправить('почтовый шаблон', 'id сайта', 'выборка свойств') -
            // по умолчанию отправляет на email указанный в админке
            CEvent::Send("RS_FLYAWAY_BUY_1_CLICK", SITE_ID, $arEventFields);
            // Сохраним в лог сообщение
            AddMessage2Log("Отправлено сообщение купить в один клик", "amo");
        }
    }
}

AddEventHandler("iblock", "OnAfterIBlockElementAdd", "OnAfterIBlockElementAddHandler");

function OnAfterIBlockElementAddHandler(&$arFields)
{
    if (!$arFields["RESULT"])
        return false;

    //выбираем id инфоблоков, которые задействованы
    if (in_array($arFields['IBLOCK_ID'], [72, 71, 70])) {

        // Сохраним в лог сообщение
        AddMessage2Log("Добавлено в инфоблок сообщение заявка с сайта или купить в один клик верх", "amo");
        $amoCrm = new newAmoCRM();
        $lead_data = array();

        $res = CIBlockElement::GetByID($arFields['ID']);
        if ($ar_res = $res->GetNext()) {
            $rsInputName = $ar_res["NAME"];
            $lead_data['NAME'] = $rsInputName;
        }
        //проверка из какой формы поступает значение (проверяем id дополнительного поля)
//        if ($arFields["IBLOCK_ID"] === 70) {
//            $lead_data['PHONE'] = $arFields['PROPERTY_VALUES'][289];
//            $lead_data['LEAD_NAME'] = 'Заявка с сайта';
//        }
        if ($arFields["IBLOCK_ID"] === 71) {
            $lead_data['PHONE'] = $arFields['PROPERTY_VALUES'][290];
            $lead_data['LEAD_NAME'] = 'Заявка с сайта';
        }
        if ($arFields["IBLOCK_ID"] === 72) {
            $lead_data['LEAD_NAME'] = 'Купить в один клик';
            $lead_data['PHONE'] = $arFields['PROPERTY_VALUES'][291];
            $lead_data['559343'] = $arFields['PROPERTY_VALUES'][292];
        }

        AddMessage2Log(print_r($lead_data, true));
        // if ($arFields["IBLOCK_ID"] == 71) {
        //   $lead_data['PHONE'] = $arFields['PROPERTY_VALUES'][290];
        // }
        // $lead_data['PHONE'] =  $arFields["PROPERTY_VALUES"]["PHONE"];
        // $lead_data['EMAIL'] = $arFields["PROPERTY_VALUES"]["EMAIL"];
        // $lead_data['COMPANY'] = $arFields["PROPERTY_VALUES"]["COMPANY"];
        // $lead_data['TEXT'] = $arFields["PROPERTY_VALUES"]["MESSAGE"]["VALUE"]["TEXT"];


        $amoCrm->add_lead($lead_data);
        AddMessage2Log("Добавлено в инфоблок сообщение заявка с сайта или купить в один клик низ", "amo");
        //newAmoCRM::add_lead($lead_data);
    }
}

AddEventHandler("sale", "OnOrderNewSendEmail", "ModifySaleMails");

function ModifySaleMails($orderID, &$eventName, &$arFields)
{
    $arOrder = CSaleOrder::GetByID($orderID);

    $order_props = CSaleOrderPropsValue::GetOrderProps($orderID);

    $phone = "";

    while ($arProps = $order_props->Fetch()) {
        if ($arProps["CODE"] == "PHONE") {
            $phone = htmlspecialchars($arProps["VALUE"]);
        }
    }

    if (!empty($arOrder["USER_DESCRIPTION"])) {
        $arFields["DESCRIPTION"] = $arOrder["USER_DESCRIPTION"];
    }

    //-- добавляем новые поля в массив результатов
    $arFields["PHONE"] = $phone;
}

//if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/d-ec-check.php"))
//require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/d-ec-check.php");
if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/include/d-ec-bitrix/d-ec.php"))
{
    include_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/php_interface/include/d-ec-bitrix/d-ec.php");
}
