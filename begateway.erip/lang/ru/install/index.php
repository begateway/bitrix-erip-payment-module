<?
$MESS["SALE_HPS_BEGATEWAY_ERIP_MODULE"] = 'Модуль платёжной системы ЕРИП';
$MESS["SALE_HPS_BEGATEWAY_ERIP_MODULE_DESC"] = 'Прием ЕРИП платежей через сервис bePaid';
$MESS["SALE_HPS_BEGATEWAY_ERIP_PARTNER_NAME"] = 'bePaid';
$MESS["SALE_HPS_BEGATEWAY_ERIP_PARTNER_URI"] = 'https://bepaid.by/';
$MESS["SALE_HPS_BEGATEWAY_ERIP_EA_STATUS"] = '[EРИП] Ожидание оплаты';
$MESS["SALE_HPS_BEGATEWAY_ERIP_EA_STATUS_DESC"] = 'Статус ожидания оплаты системы ЕРИП';
$MESS["SALE_HPS_BEGATEWAY_ERIP_EC_STATUS"] = '[EРИП] Счет отменен';
$MESS["SALE_HPS_BEGATEWAY_ERIP_EC_STATUS_DESC"] = 'Счет на оплату через ЕРИП был отменен';

$MESS["SALE_HPS_BEGATEWAY_ERIP_EA_STATUS_ERROR"] = 'Произошла ошибка при удалении статуса заказа "[ЕРИП] Ожидание оплаты" так как в системе существуют заказы с данным статусом. Удалите такие заказы или смените у них статус, а потом повторите операцию удаления модуля.';
$MESS["SALE_HPS_BEGATEWAY_ERIP_EA_STATUS_ERROR_2"] = 'Произошла ошибка при удалении статуса заказа "[ЕРИП] Ожидание оплаты"';

$MESS["SALE_HPS_BEGATEWAY_ERIP_SALE_MODULE_NOT_INSTALL_ERROR"] = "Для работы модуля требуется установленный модуль интернет-магазина";
$MESS["SALE_HPS_BEGATEWAY_ERIP_CURL_NOT_INSTALL_ERROR"] = "Для работы модуля требуется библиотека cURL";
$MESS["SALE_HPS_BEGATEWAY_ERIP_JSON_NOT_INSTALL_ERROR"] = "Для работы модуля требуется библиотека для работы с Json";

$MESS["SALE_HPS_BEGATEWAY_ERIP_COPY_ERROR_MESS"] = 'Не удалось скопировать файлы обработчика платёжной системы';
$MESS["SALE_HPS_BEGATEWAY_ERIP_ADD_ORDER_STATUS_ERROR"] = 'Не удалось создать статус заказа "[ЕРИП] Ожидание оплаты"';


$MESS["DEVTM_ERIP_PS_NAME"] = "Расчёт (ЕРИП)";
$MESS["DEVTM_ERIP_PS_DESC"] = "Система \"Расчет (ЕРИП)\" позволяет произвести оплату в любом удобном для Вас месте, в удобное для Вас время, в удобном для Вас пункте банковского обслуживания – банкомате, инфокиоске, интернет-банке, кассе банков, с помощью мобильного банкинга и т.д.
Вы можете осуществить платеж с использованием наличных денежных средств, электронных денег и банковских платежных карточек в пунктах банковского обслуживания банков, которые оказывают услуги по приему платежей, а также посредством инструментов дистанционного банковского обслуживания.";
$MESS["DEVTM_ERIP_PS_ERROR_MESS"] = "Не удалось создать платёжную систему";
$MESS["DEVTM_ERIP_STATUS_ER_NAME"] = "[EРИП] Ожидание оплаты";
$MESS["DEVTM_ERIP_STATUS_ER_DESC"] = "Статус ожидания оплаты пл. системы \"Расчёт (ЕРИП)\"";
$MESS["DEVTM_ERIP_PS_STATUS_ERROR_MESS"] = "Не удалось создать статус платёжной системы";
$MESS["DEVTM_ERIP_MAIL_EVENT_NAME"] = "Изменение статуса заказа на \"".$MESS["DEVTM_ERIP_STATUS_ER_NAME"]."\"";
$MESS["DEVTM_ERIP_MAIL_EVENT_DESC"] = "#EMAIL_TO# - EMail получателя сообщения
#NAME# - Имя клиента
#ORDER_ID# - ЕРИП-номер заказа
#SALE_NAME# - Имя магазина в системе ЕРИП
#COMPANY_NAME# - Название компании
#PATH_TO_SERVICE# - Путь к сервису
#SERVER_NAME# - Имя сайта
#SALE_EMAIL# - Email отдела продаж (устанавливается в настройках модуля интернет-магазина)
#SATE_NAME# - Название сайта (устанавливается в настройках)";
$MESS["DEVTM_ERIP_MAIL_EVENT_ADD_ERROR"] = "Не удалось добавить почтовое событие";
$MESS["DEVTM_ERIP_MAIL_TEMPLATE_THEMA"] = "#SATE_NAME#: инструкция по оплате заказа N#ORDER_ID# через Расчет (ЕРИП)";
$MESS["DEVTM_ERIP_MAIL_TEMPLATE_MESS"] = "Здравствуйте, #NAME#!<br/>
<br/>
В этом письме содержится инструкция как оплатить заказ номер #ORDER_ID# в магазине #SALE_NAME# через систему ЕРИП.<br/>
<br/>
Если Вы осуществляете платеж в кассе банка, пожалуйста, сообщите кассиру о необходимости проведения платежа через систему \"Расчет\"(ЕРИП).<br/>
<br/>
В каталоге сиcтемы \"Расчет\" услуги #COMPANY_NAME# находятся в разделе:<br/>
<br/>
#PATH_TO_SERVICE#
<br/>
<br/>
Для проведения платежа необходимо:<br/>
<br/>
1. Выбрать пункт Система \"Расчет (ЕРИП)\".<br/>
2. Выбрать последовательно вкладки: #PATH_TO_SERVICE#.<br/>
3. Ввести номер заказа #ORDER_ID#.<br/>
4. Проверить корректность информации.<br/>
5. Совершить платеж.<br/>
<br/>
<br/>
С уважением,<br/>
администрация <b><a href=\"http://#SERVER_NAME#\" style=\"color:#2e6eb6;\">Интернет-магазина</a></b><br/>
E-mail: <b><a href=\"mailto:#SALE_EMAIL#\" style=\"color:#2e6eb6;\">#SALE_EMAIL#</b>";
$MESS["DEVTM_ERIP_MAIL_TEMPLATE_ADD_ERROR"] = "Не удалось добавить почтовый шаблон";
$MESS["DEVTM_ERIP_HANDLERS_ADD_ERROR"] = "Не удалось добавить обработчик смены статуса заказа";
$MESS["DEVTM_ERIP_PS_ACTION_NAME"] = "Система 'Расчёт' (ЕРИП)";
$MESS["DEVTM_ERIP_PS_ACTION_ERROR_REG"] = "Ни один обработчик пл. системы не зарегистрирован";
