<?
$MESS["DEVTM_ERIP_MODULE_NAME"] = "Модуль платёжной системы Расчёт (ЕРИП).";
$MESS["DEVTM_ERIP_MODULE_DESC"] = "Модуль платёжной системы \"Расчёт (ЕРИП)\". Сервис bepaid";
$MESS["DEVTM_ERIP_PS_NAME"] = "Расчёт (ЕРИП)";
$MESS["DEVTM_ERIP_PS_DESC"] = "Обработчик платёжной системы \"Расчёт (ЕРИП)\", который не из настройки";
$MESS["DEVTM_ERIP_PS_ERROR_MESS"] = "Не удалось создать платёжную систему";
$MESS["DEVTM_ERIP_STATUS_ER_NAME"] = "[EРИП] Ожидание оплаты";
$MESS["DEVTM_ERIP_STATUS_ER_DESC"] = "Статус ожидания оплаты пл. системы \"Расчёт (ЕРИП)\"";
$MESS["DEVTM_ERIP_PS_STATUS_ERROR_MESS"] = "Не удалось создать статус платёжной системы";
$MESS["DEVTM_ERIP_MAIL_EVENT_NAME"] = "Изменение статуса заказа на \"".$MESS["DEVTM_ERIP_STATUS_ER_NAME"]."\"";
$MESS["DEVTM_ERIP_MAIL_EVENT_DESC"] = "#EMAIL_TO# - EMail получателя сообщения
#NAME# - Имя клиента
#ORDER_ID# - ЕРИП-номер заказа
#SALE_NAME# - Название магазина
#COMPANY_NAME# - Название компании
#PATH_TO_SERVICE# - Путь к сервису
#SERVER_NAME# - Имя сайта";
$MESS["DEVTM_ERIP_MAIL_EVENT_ADD_ERROR"] = "Не удалось добавить почтовое событие";
$MESS["DEVTM_ERIP_MAIL_TEMPLATE_THEMA"] = "#SERVER_NAME#: Изменение статуса заказа ".$MESS["DEVTM_ERIP_STATUS_ER_NAME"];
$MESS["DEVTM_ERIP_MAIL_TEMPLATE_MESS"] = "Здравствуйте, #NAME#!
В этом письме содержится инструкция как оплатить заказ номер #ORDER_ID# в магазине #SALE_NAME# через систему ЕРИП.
Если Вы осуществляете платеж в кассе банка, пожалуйста, сообщите кассиру о необходимости проведения платежа через систему \"Расчет\"(ЕРИП).
В каталоге сиcтемы \"Расчет\" услуги #COMPANY_NAME# находятся в разделе:
#PATH_TO_SERVICE#
Для проведения платежа необходимо:
1. Выбрать пункт \"Система \"Расчет (ЕРИП)\".
2. Выбрать последовательно вкладки: #PATH_TO_SERVICE#.
3. Ввести номер заказа #ORDER_ID#.
4. Проверить корректность информации.
5. Совершить платеж.";
$MESS["DEVTM_ERIP_MAIL_TEMPLATE_ADD_ERROR"] = "Не удалось добавить почтовый шаблон";
$MESS["DEVTM_ERIP_HANDLERS_ADD_ERROR"] = "Не удалось добавить обработчик смены статуса заказа";
$MESS["DEVTM_ERIP_COPY_ERROR_MESS"] = "Не удалось скопировать файлы обработчика пл. системы";
$MESS["DEVTM_ERIP_PS_ACTION_NAME"] = "Обработчик платёжной системы Расчёт(ЕРИП)";
$MESS["DEVTM_ERIP_PS_ACTION_ERROR_REG"] = "Ни один обработчик пл. системы не зарегистрирован";
$MESS["DEVTM_ERIP_DELETE_STATUS_ERROR"] = "Произошла ошибка при удалении статуса заказа [ЕРИП] Ожидание оплаты\" так как в системе существуют заказы с данной платёжной системой";
$MESS["DEVTM_ERIP_DELETE_STATUS2_ERROR"] = "Произошла ошибка при удалении статуса заказа \"[ЕРИП] Ожидание оплаты\"";
$MESS["DEVTM_ERIP_DELETE_PAMENT_ERROR"] = "Произошла ошибка при удалении платёжной системы \"Расчёт (ЕРИП)\" так как в системе существуют заказы с данной платёжной системой";
$MESS["DEVTM_ERIP_DELETE_PAMENT2_ERROR"] = "Произошла ошибка при удалении платёжной системы \"Расчёт (ЕРИП)\"";