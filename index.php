<?
$_SERVER["DOCUMENT_ROOT"] = 'определяем полный путь для крона';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$arBasketItems = array();
/* получаем все отложенные товары за текущий месяц */
$dbBasketItems = CSaleBasket::GetList(
    array(
        "NAME" => "ASC",
        "ID" => "ASC"
    ),
    array(
        "LID" => SITE_ID,
        "DELAY" => "Y",
        ">=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), mktime(0, 0, 0, date("n")-10, 1, date("Y")))
    ),
    false,
    false,
    array("ID","NAME", "PRODUCT_ID", "QUANTITY", "CAN_BUY", "PRICE", "DETAIL_PAGE_URL","DATE_INSERT","FUSER_ID")
);
$arDelay = array();
while ($arItems = $dbBasketItems->Fetch())
{
    $arDelay[$arItems['FUSER_ID']][] = $arItems['PRODUCT_ID'];
    $arFullDeleyItems[$arItems['FUSER_ID']][] = $arItems;
}

/* собираем всех юзеров, чтоб вытянуть инф одним запросом */
$arFuserId = array_keys($arDelay);

global $DB;
$dbBasketItemsPaid = CSaleBasket::GetList(
    array(
        "NAME" => "ASC",
        "ID" => "ASC"
    ),
    array(
        "FUSER_ID" => $arFuserId,
        "LID" => SITE_ID,
        ">=DATE_INSERT" => date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), mktime(0, 0, 0, date("n") - 10, 1, date("Y")))
    )
);

$arResult = array();
while ($arItemsPaid = $dbBasketItemsPaid->Fetch())
{
    if(!in_array($arDelay[$arItemsPaid['FUSER_ID']],$arItemsPaid['PRODUCT_ID'])){
        $arResult[$arItemsPaid['FUSER_ID']] = $arFullDeleyItems[$arItemsPaid['FUSER_ID']];
    }
}

$eventSend = 'DELAY_BASKET';
/* из fuser_id получаем id пользователя и подтягиваем инф о каждом */

foreach ($arResult as $fuserId => $arDelayBasket){
    $wish_list = '';
    $user_id = CSaleUser::GetUserID($fuserId);
    if((int)$user_id > 0){
        $arUser = CUser::GetByID($user_id)->Fetch();
    }
    if(!empty($arUser['EMAIL']) && check_email($arUser['EMAIL'])){
        foreach ($arDelayBasket as $arDelayProduct){
            $wish_list .= '<a href="'.$arDelayProduct['DETAIL_PAGE_URL'].'">'.$arDelayProduct['NAME'].' </a><br>';
        }
        $arSend = array(
            'WISH_LIST' => $wish_list,
            'EMAIL' => $arUser['EMAIL'],
            'NAME' => $arUser['NAME'].' '.$arUser['LAST_NAME']
        );

        CEvent::Send($eventSend,SITE_ID,$arSend);
        // в админке нужно создать событие DELAY_BASKET, к нему привязать шаблон, где будут доступны #WISH_LIST#,#EMAIL#,#NAME#
    }
}
