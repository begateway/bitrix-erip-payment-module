# Модуль 1C-Битрикс для приема ЕРИП платежей через bePaid

## Установка модуля

Подробная инструкция по настройке модуля находится по ссылке:

https://github.com/begateway/bitrix-erip-payment-module/raw/master/manual.pdf

## Журнал ошибок

Журнал ошибок по платежным системам в можно смотреть в таблице b_sale_pay_system_err_log. Если установить опцию через консоль будут записываться логи уровня debug

\Bitrix\Main\Config\Option::set('sale', 'pay_system_log_level', 0);

Посмотреть журнал ошибок можно тут http://<your_site_name>/bitrix/admin/perfmon_table.php?lang=ru&table_name=b_sale_pay_system_err_log

## Данные счета после initiatePay

Событие Битрикс `onSalePsInitiatePaySuccess` передает только объект оплаты и
не передает `ServiceResult::getData()`. Модуль сохраняет последние успешные
данные ЕРИП на время текущего запроса. Их можно получить в обработчике события:

```php
\Bitrix\Main\EventManager::getInstance()->addEventHandler(
    'sale',
    'onSalePsInitiatePaySuccess',
    function (\Bitrix\Main\Event $event) {
        $payment = $event->getParameter('payment');
        $eripData = \BeGateway\Module\Erip\PaymentData::get($payment->getId());

        // $eripData: instruction, qr_code, account_number, service_no_erip, ...
    }
);
```

Данные доступны только в том PHP-запросе, в котором был вызван
`initiatePay`. Идентификатор счета для последующих запросов сохраняется
Битрикс в поле оплаты `PS_INVOICE_ID`.

## Ссылки для разработчика

  * https://doc.budagov.ru/index.html
  * https://bxapi.ru/
  * https://mrcappuccino.ru/blog/post/work-with-order-bitrix-d7
