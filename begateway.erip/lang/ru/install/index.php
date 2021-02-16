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
$MESS["SALE_HPS_BEGATEWAY_ERIP_EC_STATUS_ERROR"] = 'Произошла ошибка при удалении статуса заказа "[EРИП] Счет отменен" так как в системе существуют заказы с данным статусом. Удалите такие заказы или смените у них статус, а потом повторите операцию удаления модуля.';
$MESS["SALE_HPS_BEGATEWAY_ERIP_EC_STATUS_ERROR_2"] = 'Произошла ошибка при удалении статуса заказа "[EРИП] Счет отменен"';

$MESS["SALE_HPS_BEGATEWAY_ERIP_SALE_MODULE_NOT_INSTALL_ERROR"] = "Для работы модуля требуется установленный модуль интернет-магазина";
$MESS["SALE_HPS_BEGATEWAY_ERIP_CURL_NOT_INSTALL_ERROR"] = "Для работы модуля требуется библиотека cURL";
$MESS["SALE_HPS_BEGATEWAY_ERIP_JSON_NOT_INSTALL_ERROR"] = "Для работы модуля требуется библиотека для работы с Json";

$MESS["SALE_HPS_BEGATEWAY_ERIP_COPY_ERROR_MESS"] = 'Не удалось скопировать файлы обработчика платёжной системы';
$MESS["SALE_HPS_BEGATEWAY_ERIP_ADD_ORDER_STATUS_ERROR"] = 'Не удалось создать статусы заказов"';
$MESS["SALE_HPS_BEGATEWAY_ERIP_HANDLERS_ADD_ERROR"] = "Ошибка регистрации обработчика смены статуса заказа";
$MESS["SALE_HPS_BEGATEWAY_ERIP_HANDLERS_DELETE_ERROR"] = "Ошибка удаления обработчика смены статуса заказа";

$MESS["SALE_HPS_BEGATEWAY_ERIP_MAIL_EVENT_ADD_ERROR"] = "Не удалось добавить почтовое событие";
$MESS["SALE_HPS_BEGATEWAY_ERIP_MAIL_TEMPLATE_ADD_ERROR"] = "Не удалось добавить почтовый шаблон";
$MESS['SALE_HPS_BEGATEWAY_ERIP_MAIL_TEMPLATE_CHECKOUT_DESCRIPTION'] = 'Услугу предоставляет сервис <b>&laquo;bePaid&raquo;</b>.';
$MESS["SALE_HPS_BEGATEWAY_ERIP_MAIL_TEMPLATE_SUBJECT"] = "#SITE_NAME#: инструкция по оплате заказа N#ORDER_ID# через ЕРИП";
$MESS["SALE_HPS_BEGATEWAY_ERIP_MAIL_EVENT_NAME"] = "Изменение статуса заказа на \"".$MESS["SALE_HPS_BEGATEWAY_ERIP_EA_STATUS"]."\"";
$MESS["SALE_HPS_BEGATEWAY_ERIP_MAIL_EVENT_DESC"] = "
#ORDER_ID# - код заказа
#ORDER_NUMBER# - номер заказа
#PAYMENT_ID# - ID оплаты
#PAYMENT_NUMBER# - номер оплаты
#ORDER_ACCOUNT_NUMBER_ENCODE# - код заказа(для ссылок)
#ORDER_REAL_ID# - реальный ID заказа
#ORDER_DATE# - дата заказа
#EMAIL# - E-Mail пользователя
#ORDER_PUBLIC_URL# - ссылка для просмотра заказа без авторизации (требуется настройка в модуле интернет-магазина)
#SALE_EMAIL# - E-Mail адрес по умолчанию (устанавливается в настройках)
#SITE_NAME# - Название сайта (устанавливается в настройках)
#SERVER_NAME# - URL сервера (устанавливается в настройках)
#INSTRUCTION# - путь в дереве ЕРИП для оплаты
#ACCOUNT_NUMBER# - номер для оплаты в ЕРИП
#ERIP_SERVICE_CODE# - код услуги ЕРИП
#QR_CODE# - QR-код для оплаты";

$MESS["SALE_HPS_BEGATEWAY_ERIP_MAIL_TEMPLATE_TEXT"] = '
Инструкция по оплате заказа через ЕРИП

Если Вы осуществляете платеж в кассе банка, пожалуйста, сообщите кассиру о необходимости проведения платежа через ЕРИП.

Для проведения платежа необходимо найти магазин в дереве ЕРИП по коду услуги #ERIP_SERVICE_CODE# или воспользоваться инструкцией:

1. Выбрать пункт ЕРИП
2. Выбрать последовательно пункты: #INSTRUCTION#
3. Ввести номер <strong>#ACCOUNT_NUMBER#
4. Проверить корректность информации
5. Совершить платеж

Для получения подробной информации по заказу пройдите на сайт #ORDER_PUBLIC_URL#

С уважением,
администрация Интернет-магазина
#SALE_EMAIL#
';

$MESS["SALE_HPS_BEGATEWAY_ERIP_MAIL_TEMPLATE_HTML"] = '
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ru" lang="ru">
<head>
	<meta http-equiv="Content-Type" content="text/html;charset=#SITE_CHARSET#"/>
	<style>
		body
		{
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
			font-size: 14px;
			color: #000;
		}
	</style>
</head>
<body>
<table cellpadding="0" cellspacing="0" width="850" style="background-color: #d1d1d1; border-radius: 2px; border:1px solid #d1d1d1; margin: 0 auto;" border="1" bordercolor="#d1d1d1">
	<tr>
		<td height="83" width="850" bgcolor="#eaf3f5" style="border: none; padding-top: 23px; padding-right: 17px; padding-bottom: 24px; padding-left: 17px;">
			<table cellpadding="0" cellspacing="0" border="0" width="100%">
				<tr>
					<td bgcolor="#ffffff" height="75" style="font-weight: bold; text-align: center; font-size: 26px; color: #0b3961;">Инструкция по оплате заказа через ЕРИП</td>
				</tr>
				<tr>
					<td bgcolor="#bad3df" height="11" style="font-weight: bold; text-align: center;">#ABOUT_SERVICE#</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td width="850" bgcolor="#f7f7f7" valign="top" style="border: none; padding-top: 0; padding-right: 44px; padding-bottom: 16px; padding-left: 44px;">
			<p style="margin-top:30px; margin-bottom: 28px; font-weight: bold; font-size: 19px;">Если Вы осуществляете платеж в кассе банка, пожалуйста, сообщите кассиру о необходимости проведения платежа через ЕРИП.</p>
      <p style="margin-top:30px; margin-bottom: 28px;">Для проведения платежа необходимо найти магазин в дереве ЕРИП по коду услуги <strong>#ERIP_SERVICE_CODE#</strong> или воспользоваться инструкцией:</p>
      <p>
        <ol>
          <li>Выбрать пункт ЕРИП</li>
          <li>Выбрать последовательно пункты: <i>#INSTRUCTION#</i></li>
          <li>Ввести номер <strong>#ACCOUNT_NUMBER#</strong></li>
          <li>Проверить корректность информации</li>
          <li>Совершить платеж</li>
        </ol>
      </p>
			<p style="margin-top: 30px; margin-bottom: 20px;">Если вы пользуйтесь мобильными приложением банка, то используйте его, чтобы отсканировать QR-код и осуществить платеж.</p>
      <p><img src="#QR_CODE#"></p>
			<p style="margin-top: 30px; margin-bottom: 20px; line-height: 20px;">Для получения подробной информации по заказу пройдите на сайт <a href="#ORDER_PUBLIC_URL#">#ORDER_PUBLIC_URL#</a></p>
		</td>
	</tr>
	<tr>
		<td height="40px" width="850" bgcolor="#f7f7f7" valign="top" style="border: none; padding-top: 0; padding-right: 44px; padding-bottom: 30px; padding-left: 44px;">
			<p style="border-top: 1px solid #d1d1d1; margin-bottom: 5px; margin-top: 0; padding-top: 20px; line-height:21px;">С уважением,<br />администрация <a href="http://#SERVER_NAME#" style="color:#2e6eb6;">Интернет-магазина</a><br />
				E-mail: <a href="mailto:#SALE_EMAIL#" style="color:#2e6eb6;">#SALE_EMAIL#</a>
			</p>
		</td>
	</tr>
</table>
</body>
</html>';
