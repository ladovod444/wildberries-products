<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Wildberries\Products\Messenger\Cards\CardStocks;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Repository\ProductTotalInOrders\ProductTotalInOrdersInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByConstInterface;
use BaksDev\Products\Product\Type\Barcode\ProductBarcode;
use BaksDev\Products\Stocks\BaksDevProductsStocksBundle;
use BaksDev\Wildberries\Products\Api\Cards\FindAllWildberriesCardsRequest;
use BaksDev\Wildberries\Products\Api\Cards\WildberriesCardDTO;
use BaksDev\Wildberries\Products\Api\Stocks\GetWbFbsStocksRequest;
use BaksDev\Wildberries\Products\Api\Stocks\UpdateWbFbsStocksRequest;
use BaksDev\Wildberries\Products\Repository\Cards\CurrentWildberriesProductsCard\WildberriesProductsCardInterface;
use BaksDev\Wildberries\Products\Repository\Cards\CurrentWildberriesProductsCard\WildberriesProductsCardResult;
use BaksDev\Wildberries\Repository\AllWbTokensByProfile\AllWbTokensByProfileInterface;
use BaksDev\Yandex\Market\Products\Api\Products\Stocks\UpdateYaMarketProductStocksRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class WildberriesProductsStocksDispatcher
{
    public function __construct(
        #[Target('wildberriesProductsLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private ProductTotalInOrdersInterface $ProductTotalInOrders,
        private WildberriesProductsCardInterface $WildberriesProductsCardRepository,
        private GetWbFbsStocksRequest $GetWbFbsStocksRequest,
        private UpdateWbFbsStocksRequest $UpdateWbFbsStocksRequest,
        private FindAllWildberriesCardsRequest $FindAllWildberriesCardsRequest,
        private DeduplicatorInterface $Deduplicator,
    ) {}

    /**
     * Обновляем остатки товаров Wildberries
     */
    public function __invoke(WildberriesProductsStocksMessage $message): void
    {


        /**
         * Не обновляем остатки если отключены
         */

        $isStock = $this->GetWbFbsStocksRequest
            ->forTokenIdentifier($message->getIdentifier())
            ->isStock();

        if(false === $isStock)
        {
            return;
        }

        /**
         * Получаем карточку товара Wildberries для остатка
         */

        $WildberriesProductsCardResult = $this->WildberriesProductsCardRepository
            ->forProfile($message->getProfile())
            ->forProduct($message->getProduct())
            ->forOfferConst($message->getOfferConst())
            ->forVariationConst($message->getVariationConst())
            ->forModificationConst($message->getModificationConst())
            ->find();

        if(false === ($WildberriesProductsCardResult instanceof WildberriesProductsCardResult))
        {
            return;
        }

        /**
         * Не обновляем остатки без обязательных параметров
         */
        if(false === $WildberriesProductsCardResult->isCredentials())
        {
            return;
        }

        /**  Остаток товара в карточке либо если подключен модуль складского учета - остаток на складе */
        $ProductQuantity = $WildberriesProductsCardResult->getProductQuantity();

        /** Если подключен модуль складского учета - расчет согласно необработанных заказов */
        if(class_exists(BaksDevProductsStocksBundle::class))
        {
            /** Получаем количество необработанных заказов */
            $unprocessed = $this->ProductTotalInOrders
                ->onProfile($message->getProfile())
                ->onProduct($message->getProduct())
                ->onOfferConst($message->getOfferConst())
                ->onVariationConst($message->getVariationConst())
                ->onModificationConst($message->getModificationConst())
                ->findTotal();

            $ProductQuantity -= $unprocessed;
        }

        $ProductQuantity = max($ProductQuantity, 0);


        /**
         * Отсеиваем повторяющиеся запросы
         */

        $Deduplicator = $this->Deduplicator
            ->namespace('module-name')
            ->expiresAfter('1 minutes')
            ->deduplication([$message, $ProductQuantity]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $Deduplicator->save();


        // -----------------------------------------------------------

        $result = $this->FindAllWildberriesCardsRequest
            ->forTokenIdentifier($message->getIdentifier())
            ->findAll($WildberriesProductsCardResult->getSearchArticle());

        if(false === $result || false === $result->valid())
        {
            $this->logger->critical(sprintf(
                'wildberries-products: Карточка товара Wildberries по артикулу %s не найдена',
                $WildberriesProductsCardResult->getSearchArticle(),
            ), [self::class.':'.__LINE__]);

            return;
        }

        /** @var WildberriesCardDTO $WildberriesCardDTO */
        $WildberriesCardDTO = $result->current();
        $chrt = $WildberriesCardDTO->getChrt('0');

        /** Обновляем остатки товара если наличие изменилось */
        $isUpdate = $this->UpdateWbFbsStocksRequest
            ->forTokenIdentifier($message->getIdentifier())
            ->fromChrtId($chrt)
            ->setArticle($WildberriesProductsCardResult->getSearchArticle())
            ->setTotal($ProductQuantity)
            ->update();

        if(true === $isUpdate)
        {
            $this->logger->info(sprintf(
                '%s: Обновили наличие  => %s',
                $WildberriesProductsCardResult->getSearchArticle(),
                $ProductQuantity,
            ), [$message->getIdentifier()]);
        }
    }
}
