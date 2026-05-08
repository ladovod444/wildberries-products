<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Wildberries\Products\Controller\Admin\Barcode;

use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByEventInterface;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByEventResult;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Wildberries\Products\Repository\Barcode\WbBarcodeProperty\WbBarcodePropertyByProductEventInterface;
use BaksDev\Wildberries\Products\Repository\Barcode\WbBarcodeSettings\WbBarcodeSettingsInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity(['ROLE_WB_BARCODE_PRINT', 'ROLE_ORDERS'])]
final class PrintController extends AbstractController
{
    /**
     * Штрихкод Wildberries карточки
     */
    #[Route('/admin/wb/barcode/print/{product}/{offer}/{variation}/{modification}', name: 'admin.barcode.print', methods: ['GET'])]
    public function index(
        Request $request,
        #[Target('wildberriesProductsLogger')] LoggerInterface $logger,
        WbBarcodeSettingsInterface $WbBarcodeSettings,
        ProductDetailByEventInterface $ProductDetailByUid,
        WbBarcodePropertyByProductEventInterface $wbBarcodeProperty,
        BarcodeWrite $BarcodeWrite,
        #[ParamConverter(ProductEventUid::class, key: 'product')] ProductEventUid $event,
        #[ParamConverter(ProductOfferUid::class, key: 'offer')] ?ProductOfferUid $offer = null,
        #[ParamConverter(ProductVariationUid::class, key: 'variation')] ?ProductVariationUid $variation = null,
        #[ParamConverter(ProductModificationUid::class, key: 'modification')] ?ProductModificationUid $modification = null,

    ): Response
    {
        /**
         * Получаем информацию о продукте
         */

        $ProductDetailByEventResult = $ProductDetailByUid
            ->event($event)
            ->offer($offer)
            ->variation($variation)
            ->modification($modification)
            ->findResult();

        if(false === ($ProductDetailByEventResult instanceof ProductDetailByEventResult))
        {
            $logger->critical(
                'wildberries-products: Продукция в упаковке не найдена',
                [
                    'event' => $event,
                    'offer' => $offer,
                    'variation' => $variation,
                    'modification' => $modification,
                    self::class.':'.__LINE__],
            );

            return new Response('Продукция в упаковке не найдена', Response::HTTP_NOT_FOUND);
        }

        if(empty($ProductDetailByEventResult->getProductBarcode()))
        {
            $logger->critical(
                sprintf('%s: Не указан штрихкод продукта в карточке', $ProductDetailByEventResult->getProductArticle()),
                [self::class.':'.__LINE__],
            );

            return new Response('Не указан штрихкод продукта', Response::HTTP_NOT_FOUND);
        }

        /**
         * Генерируем штрихкод продукции (один на все заказы)
         */

        $barcode = $BarcodeWrite
            ->text($ProductDetailByEventResult->getProductBarcode())
            ->type(BarcodeType::Code128)
            ->format(BarcodeFormat::SVG)
            ->generate();

        if($barcode === false)
        {
            /**
             * Проверить права на исполнение
             * chmod +x /home/bundles.baks.dev/vendor/baks-dev/barcode/Writer/Generate
             * chmod +x /home/bundles.baks.dev/vendor/baks-dev/barcode/Reader/Decode
             * */
            throw new RuntimeException('Barcode write error');
        }

        $render = $BarcodeWrite->render();
        $BarcodeWrite->remove();
        $render = strip_tags($render, ['path']);
        $render = trim($render);

        /**
         * Получаем настройки бокового стикера
         */

        $WbBarcodeSettingsResult = $WbBarcodeSettings
            ->forProduct($ProductDetailByEventResult->getProductId())
            ->find();

        return $this->render(
            parameters: [
                'barcode' => $render,
                'settings' => $WbBarcodeSettingsResult,
                'total' => $request->query->get('total', 1),
                'product' => $ProductDetailByEventResult,
            ],
            file: 'print.html.twig',
        );
    }
}