# Модуль ConcordPay для Joomla Phoca Cart

Creator: [ConcordPay](https://concordpay.concord.ua)<br>
Tags: ConcordPay, Joomla, Phoca Cart, payment, payment gateway, credit card, Visa, Masterсard, Apple Pay, Google Pay<br>
Requires at least: Joomla 4.0, Phoca Cart 4.0<br>
License: GNU GPL v3.0<br>
License URI: [License](https://opensource.org/licenses/GPL-3.0)

Этот модуль позволит вам принимать платежи через платёжную систему **ConcordPay**.

Для работы модуля у вас должны быть установлены **CMS Joomla 4.x** и модуль электронной коммерции **Phoca Cart 4.x**.

## Установка

1. В административной части сайта перейти в *«Система -> (Установка) Расширения -> Загрузить файл пакета»* и загрузить архив с модулем,
   который находится в папке `package`.

2. Перейти в *«Система -> (Управление) Расширения»*, найти и включить плагин *«Phoca Cart Payment - ConcordPay Plugin»*.

3. Перейти в *«Компоненты -> Phoca Cart -> Payment»*, где добавить новый способ оплаты **«ConcordPay»**.

4. На вкладке *«General options»* из выпадающего списка *«Payment method»* выбрать **ConcordPay**.

5. Ниже, в диалоге загрузки *«Image»* загрузить логотип метода оплаты (файл логотипа находится в архиве с модулем).

6. На вкладке *«Payment method options»* установить необходимые настройки плагина.<br>

   Указать данные, полученные от платёжной системы:
    - *Идентификатор продавца (Merchant ID)*;
    - *Секретный ключ (Secret Key)*.

   Также установить статусы заказов на разных этапах их существования.

7. На вкладке *«Published options»* выберите *«Published»*: **Опубликовано**.

8. Сохранить настройки модуля.

Модуль готов к работе.

*Модуль Joomla Phoca Cart протестирован для работы с Joomla 4.0.6, Phoca Cart 4.0.0 и PHP 7.4.*