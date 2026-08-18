<?php
namespace BeGateway\Module\Erip;

/**
 * Keeps the latest successful initiatePay payload for the current request.
 *
 * Bitrix's onSalePsInitiatePaySuccess event only exposes the Payment object and
 * drops ServiceResult::getData(). Event listeners can use this class to read
 * the ERIP template data after that event has fired:
 *
 * PaymentData::get($payment->getId());
 */
final class PaymentData
{
  private static $data = [];

  public static function set($paymentId, array $data): void
  {
    self::$data[(int)$paymentId] = $data;
  }

  public static function get($paymentId): ?array
  {
    $paymentId = (int)$paymentId;
    return isset(self::$data[$paymentId]) ? self::$data[$paymentId] : null;
  }

  public static function clear($paymentId): void
  {
    unset(self::$data[(int)$paymentId]);
  }
}
