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

namespace BaksDev\Wildberries\Products\Messenger\Cards\CardNew;


use BaksDev\DeliveryTransport\BaksDevDeliveryTransportBundle;
use BaksDev\DeliveryTransport\Entity\ProductParameter\DeliveryPackageProductParameter;
use BaksDev\DeliveryTransport\Type\ProductParameter\Weight\Kilogram\Kilogram;
use BaksDev\DeliveryTransport\UseCase\Admin\ProductParameter\DeliveryPackageProductParameterDTO;
use BaksDev\DeliveryTransport\UseCase\Admin\ProductParameter\DeliveryPackageProductParameterHandler;
use BaksDev\Products\Category\Repository\SettingsByCategory\SettingsByCategoryInterface;
use BaksDev\Products\Category\Type\Offers\Id\CategoryProductOffersUid;
use BaksDev\Products\Category\Type\Offers\Variation\CategoryProductVariationUid;
use BaksDev\Products\Category\Type\Section\Field\Id\CategoryProductSectionFieldUid;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Repository\ExistProductArticle\ExistProductArticleInterface;
use BaksDev\Products\Product\Repository\ProductByArticle\ProductEventByArticleInterface;
use BaksDev\Products\Product\Type\Barcode\ProductBarcode;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Category\CategoryCollectionDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Offers\Barcode\ProductOfferBarcodeDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Offers\Image\ProductOfferImageCollectionDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Offers\ProductOffersCollectionDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Offers\Variation\Barcode\ProductVariationBarcodeDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Offers\Variation\Image\ProductVariationImageCollectionDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Offers\Variation\Modification\Image\ProductModificationImageCollectionDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Offers\Variation\Modification\ProductModificationCollectionDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Offers\Variation\ProductVariationCollectionDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Photo\PhotoCollectionDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\ProductDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\ProductHandler;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Profile\CollectionProductProfileDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Project\Description\ProductProjectDescriptionDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Property\PropertyCollectionDTO;
use BaksDev\Products\Product\UseCase\Admin\NewEdit\Trans\ProductTransDTO;
use BaksDev\Reference\Color\Choice\ReferenceChoiceColor;
use BaksDev\Wildberries\Products\Api\Cards\FindAllWildberriesCardsRequest;
use BaksDev\Wildberries\Products\Api\Cards\WildberriesCardDTO;
use BaksDev\Wildberries\Products\Api\GetWildberriesCardImage;
use BaksDev\Wildberries\Products\Mapper\Params\Collection\ColorWildberriesProductParameters;
use BaksDev\Wildberries\Products\Repository\Settings\ProductSettingsCategory\ProductSettingsCategoryInterface;
use BaksDev\Wildberries\Products\Repository\Settings\ProductSettingsCategoryParameters\ProductSettingsCategoryParametersInterface;
use BaksDev\Wildberries\Products\Repository\Settings\ProductSettingsCategoryProperty\ProductSettingsCategoryPropertyInterface;
use BaksDev\Wildberries\Products\Type\Settings\Property\WildberriesProductProperty;
use Doctrine\ORM\Mapping\Table;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Создает новые карточки товаров в системе
 */
#[AsMessageHandler(priority: 0)]
final readonly class WildberriesCardNewDispatcher
{
    public function __construct(
        #[Target('wildberriesProductsLogger')] private LoggerInterface $logger,
        #[AutowireIterator('baks.reference.choice')] private iterable $reference,
        #[AutowireIterator('baks.fields.choice')] private iterable $fields,
        private FindAllWildberriesCardsRequest $WildberriesCardsRequest,
        private SettingsByCategoryInterface $SettingsByCategory,
        private ExistProductArticleInterface $ExistProductArticle,
        private GetWildberriesCardImage $WildberriesCardImage,
        private ProductHandler $ProductHandler,
        private DeliveryPackageProductParameterHandler $DeliveryPackageProductParameterHandler,
        private ProductSettingsCategoryInterface $ProductSettingsCategory,
        private ProductSettingsCategoryPropertyInterface $ProductSettingsCategoryProperty,
        private ProductSettingsCategoryParametersInterface $ProductSettingsCategoryParameters,
        private ProductEventByArticleInterface $ProductEventByArticle,
    ) {}

    public function __invoke(WildberriesCardNewMassage $message): void
    {
        $WildberriesCards = $this->WildberriesCardsRequest
            ->forTokenIdentifier($message->getTokenIdentifier())
            ->findAll($message->getArticle());

        /** @var WildberriesCardDTO $WildberriesCardDTO */
        foreach($WildberriesCards as $WildberriesCardDTO)
        {


            /** DEBUG фильтр категории  */

            //            if(false === stripos($WildberriesCardDTO->getArticle(), 'FSWOMEN-0203'))
            //            {
            //                continue;
            //            }

            /**
             * Если передан артикул - применяем фильтр по вхождению
             */
            if($message->getArticle() && stripos($WildberriesCardDTO->getArticle(), $message->getArticle()) === false)
            {
                continue;
            }

            /* Пропускаем, если карточка без фото */
            if($WildberriesCardDTO->countMedia() === 0)
            {
                $this->logger->warning(
                    sprintf('Торговое предложение с артикулом %s без фото', $WildberriesCardDTO->getArticle()),
                );

                continue;
            }


            /**
             * Получаем идентификатор системной категории
             */
            $CategoryProductUid = $this->ProductSettingsCategory
                ->category($WildberriesCardDTO->getCategory())
                ->find();

            if(false === $CategoryProductUid)
            {
                $this->logger->warning(
                    sprintf('Для товара с артикулом %s не найдено настройки соотношений',
                        $WildberriesCardDTO->getArticle()));
                continue;
            }


            /**
             * Получаем настройки системной категории
             */
            $SettingsByCategory = $this->SettingsByCategory
                ->category($CategoryProductUid)
                ->find();


            /** Формируем артикул карточки товара  */

            $cardArticle = $WildberriesCardDTO->getArticle();

            // фильтруем артикул по пробелам
            $cardArticleSpace = explode(' ', $cardArticle);
            $cardArticle = current($cardArticleSpace);

            $cardArticlePostfix = null;

            if(count($cardArticleSpace) > 1)
            {
                array_shift($cardArticleSpace);

                $cardArticlePostfix = implode(' ', $cardArticleSpace);
            }

            $cardArticle = explode('-', $cardArticle);

            if($SettingsByCategory['variation_article'] || $SettingsByCategory['offer_article'])
            {
                count($cardArticle) === 1 ?: array_pop($cardArticle);
            }

            if($SettingsByCategory['modification_article'])
            {
                count($cardArticle) === 1 ?: array_pop($cardArticle);
                count($cardArticle) === 1 ?: array_pop($cardArticle);
            }

            $cardArticle = implode('-', $cardArticle);


            /**
             * Проверяем карточку с соответствующим корневым артикулом по профилю
             */
            $isCardProfile = true; // область видимости только по профилю

            $ProductEvent = $this->ProductEventByArticle
                ->onlyCard() // проверяем только артикул карточки
                ->forProfile($message->getProfile())
                ->findProductEventByArticle($cardArticle);

            /** Если карточка по профилю не найдена - пробуем найти общую */
            if(false === ($ProductEvent instanceof ProductEvent))
            {
                $ProductEvent = $this->ProductEventByArticle
                    ->onlyCard()
                    ->forProfile(false)
                    ->findProductEventByArticle($cardArticle);

                if(true === ($ProductEvent instanceof ProductEvent))
                {
                    $isCardProfile = false; // общая область видимости только по профилю
                }
            }


            /**
             * Проверяем что в ней отсутствует артикул торговых предложения с размерами
             */

            match (true)
            {
                $SettingsByCategory['offer_article'] => $this->ExistProductArticle->onlyOffer(),
                $SettingsByCategory['variation_article'] => $this->ExistProductArticle->onlyVariation(),
                $SettingsByCategory['modification_article'] => $this->ExistProductArticle->onlyModification(),
            };

            foreach($WildberriesCardDTO->getOffersCollection() as $offer)
            {
                $article = $WildberriesCardDTO->getArticle().'-'.$offer;

                if(true === $this->ExistProductArticle->exist($article))
                {
                    continue 2;
                }
            }


            $ProductDTO = new ProductDTO();
            false === ($ProductEvent instanceof ProductEvent) ?: $ProductEvent->getDto($ProductDTO);

            /** Всегда переопределяем категорию */

            $CategoryCollectionDTO = new CategoryCollectionDTO();
            $CategoryCollectionDTO->setCategory($CategoryProductUid);
            $CategoryCollectionDTO->setRoot(true);
            $ProductDTO->resetCategory()->addCategory($CategoryCollectionDTO);


            /**
             * Присваиваем неизменную информацию о продукте
             *
             * @see InfoDTO
             */
            $ProductInfo = $ProductDTO->getInfo();
            $ProductDTO->setInfo($ProductInfo);
            $ProductInfo->getUrl() ?: $ProductInfo->setUrl(uniqid('', false));
            $ProductInfo->setArticle($cardArticle);

            /**
             * Присваиваем область видимости карточки
             */
            if(true === $isCardProfile)
            {
                $CollectionProductProfileDTO = new CollectionProductProfileDTO()
                    ->setValue($message->getProfile());

                $ProductDTO->addProfile($CollectionProductProfileDTO);

            }


            /** Создаем первоначально название */
            $title = $WildberriesCardDTO->getName();

            /* Фильтруем в названии категорию */
            $caseCategory = WildberriesProductProperty::caseCategory();

            if(isset($caseCategory[$WildberriesCardDTO->getCategory()]))
            {
                $cats = $caseCategory[$WildberriesCardDTO->getCategory()];
                $title = $this->filterTitle($cats, $title);
            }

            $reference = iterator_to_array($this->reference);
            $fields = iterator_to_array($this->fields);


            /**
             * Торговые предложения
             */

            // Создаем массив идентификаторов упаковки
            $package = null;


            //            $newSize = null;
            //
            //            foreach($WildberriesCardDTO->getOffersCollection() as $key => $offer)
            //            {
            //                $article = $WildberriesCardDTO->getArticle().'-'.$offer;
            //
            //                if(true === $this->ExistProductArticle->exist($article))
            //                {
            //                    continue;
            //                }
            //
            //                $newSize[$key] = $offer;
            //            }


            if(false === empty($SettingsByCategory['offer_id']))
            {
                $OffersDTO = new ProductOffersCollectionDTO();
                $ProductDTO->addOffer($OffersDTO);
                $OffersDTO->setCategoryOffer(new CategoryProductOffersUid($SettingsByCategory['offer_id']));

                foreach($WildberriesCardDTO->getOffersCollection() as $barcode => $size)
                {
                    /** Идентификаторы параметров упаковки по умолчанию */
                    $package[$barcode]['offer'] = null;
                    $package[$barcode]['variation'] = null;
                    $package[$barcode]['modification'] = null;

                    // поиск цвета по идентификатору (временно для футболок)
                    $CharValue = $WildberriesCardDTO->getCharacteristic(ColorWildberriesProductParameters::ID);

                    if(empty($CharValue))
                    {
                        $this->logger->critical(
                            sprintf('wildberries-products: значение по идентификатору %s не найдено', ColorWildberriesProductParameters::ID),
                            [$size,],
                        );

                        continue;
                    }

                    /**
                     * @note Если указана библиотека, и значение не найдено - будет присвоено значение, и записан лог с значением
                     */
                    if($SettingsByCategory['offer_reference'])
                    {
                        /** @var ReferenceChoiceColor $offerReference */
                        $offerReference = array_find($reference, static function($item) use ($SettingsByCategory) {
                            return $item->equals($SettingsByCategory['offer_reference']);
                        });

                        if(false === is_null($offerReference))
                        {
                            $CharValue = new ($offerReference->class())($CharValue);

                            // Фильтруем торговое предложение в названии
                            $title = $CharValue->filter($title);
                        }
                    }


                    $OffersDTO->setName($title.($cardArticlePostfix ? ' '.$cardArticlePostfix : ''));


                    // Если торговое предложение является артикульным - присваиваем артикул и баркод
                    if($SettingsByCategory['offer_article'])
                    {
                        $offerArticle = $WildberriesCardDTO->getArticle().(empty($size) ? '' : '-'.$size);

                        /** Проверяем что данный артикул торгового предложения не был добавлен ранее */
                        foreach($ProductDTO->getOffer() as $ProductOffersElement)
                        {
                            if($ProductOffersElement->getArticle() === $offerArticle)
                            {
                                return;
                            }
                        }

                        $ProductOfferBarcodeDTO = new ProductOfferBarcodeDTO()
                            ->setValue(new ProductBarcode((string) $barcode));

                        $OffersDTO
                            ->setArticle($offerArticle)
                            ->addBarcode($ProductOfferBarcodeDTO);
                    }

                    $OffersDTO->setValue((string) $CharValue);
                    $OffersDTO->getConst(); // генерируем константу

                    $package[$barcode]['offer'] = $OffersDTO->getConst();


                    /** Загрузка изображений */
                    if($SettingsByCategory['offer_image'])
                    {
                        foreach($WildberriesCardDTO->getMedia() as $media)
                        {
                            $this->createMediaFile($OffersDTO, ProductOfferImage::class, $media);
                        }
                    }

                    /**
                     * Множественные варианты
                     */

                    if(false === $SettingsByCategory['variation_id'])
                    {
                        continue;
                    }


                    // поиск цвета по идентификатору (временно для футболок)
                    $CharValueVariation = $size;


                    // Присваиваем значение из SIZE (ВРЕМЕННО ДЛЯ ФУТБОЛОК - РАЗМЕР)
                    if($SettingsByCategory['variation_reference'])
                    {
                        /** @var ReferenceChoiceColor $offerReference */
                        $variationReference = array_find($reference, static function($item) use ($SettingsByCategory) {
                            return $item->equals($SettingsByCategory['variation_reference']);
                        });

                        if(false === is_null($variationReference))
                        {
                            $CharValueVariation = new ($variationReference->class())($CharValueVariation);

                            // Фильтруем множественный вариант в названии
                            $title = $CharValue->filter($title);
                        }
                    }


                    $ProductVariationCollectionDTO = $OffersDTO->getVariation();

                    $filterVariation = $ProductVariationCollectionDTO->filter(function($element) use (
                        $CharValueVariation
                    ) {
                        return $element->getValue() === (string) $CharValueVariation;
                    });

                    /** @var ProductVariationCollectionDTO $VariationDTO */
                    $VariationDTO = $filterVariation->current();

                    if(false === $VariationDTO)
                    {
                        $VariationDTO = new ProductVariationCollectionDTO();
                        $OffersDTO->addVariation($VariationDTO);
                        $VariationDTO->setCategoryVariation(new CategoryProductVariationUid($SettingsByCategory['variation_id']));
                    }

                    // Если множественный вариант является артикульным - присваиваем артикул и баркод
                    if($SettingsByCategory['variation_article'])
                    {
                        $variationArticle = $WildberriesCardDTO->getArticle().(empty($size) ? '' : '-'.$size);

                        /** Проверяем что данный артикул множественного варианта торгового предложения не был добавлен ранее */
                        foreach($ProductVariationCollectionDTO as $ProductVariationElement)
                        {
                            if($ProductVariationElement->getArticle() === $variationArticle)
                            {
                                return;
                            }
                        }

                        $ProductVariationBarcodeDTO = new ProductVariationBarcodeDTO()
                            ->setValue(new ProductBarcode((string) $barcode));

                        $VariationDTO
                            ->setArticle($WildberriesCardDTO->getArticle().(empty($size) ? '' : '-'.$size))
                            ->addBarcode($ProductVariationBarcodeDTO);
                    }

                    $VariationDTO->setValue((string) $CharValueVariation);
                    $VariationDTO->getConst(); // генерируем константу

                    $package[$barcode]['variation'] = $VariationDTO->getConst();

                    /** Загрузка изображений */
                    if($SettingsByCategory['variation_image'])
                    {
                        foreach($WildberriesCardDTO->getMedia() as $media)
                        {
                            $this->createMediaFile($VariationDTO, ProductVariationImage::class, $media);
                        }
                    }


                    /**
                     * Модификация множественного варианта
                     */

                }

            }

            /**
             * Характеристики карточки
             */

            foreach($WildberriesCardDTO->getCharacteristicsCollection() as $id => $CharValueProperty)
            {

                $CategoryProductSectionFieldUid = $this->ProductSettingsCategoryParameters
                    ->category($WildberriesCardDTO->getCategory())
                    ->find((int) $id);

                if(false === $CategoryProductSectionFieldUid)
                {
                    $CategoryProductSectionFieldUid = $this->ProductSettingsCategoryProperty
                        ->category($WildberriesCardDTO->getCategory())
                        ->find((string) $id);
                }


                if($CategoryProductSectionFieldUid instanceof CategoryProductSectionFieldUid)
                {
                    /** Проверяем нет ли свойства */
                    $ProductPropertyFilter = $ProductDTO->getPropertyCollection()
                        ->filter(function(PropertyCollectionDTO $element) use ($CategoryProductSectionFieldUid) {
                            return $element->getField()?->equals($CategoryProductSectionFieldUid);
                        });

                    $PropertyCollectionDTO = $ProductPropertyFilter->current();

                    if(false === $PropertyCollectionDTO)
                    {
                        $PropertyCollectionDTO = new PropertyCollectionDTO();
                        $ProductDTO->addProperty($PropertyCollectionDTO);
                        $PropertyCollectionDTO->setField($CategoryProductSectionFieldUid);
                    }


                    /** Пробуем найти соответствующее свойство  */
                    $propertyFields = array_find($fields, static function($item) use ($CategoryProductSectionFieldUid) {
                        return $item->equals($CategoryProductSectionFieldUid->getAttr());
                    });

                    if($propertyFields)
                    {
                        $CharValueProperty = new ($propertyFields->class())((string) $CharValueProperty);

                        if(method_exists($CharValueProperty, 'filter'))
                        {
                            $title = $CharValueProperty->filter($title);
                        }
                    }

                    $PropertyCollectionDTO->setValue((string) $CharValueProperty);

                }
            }

            /** Загрузка изображений в галлерею */
            if(isset($SettingsByCategory['offer_image'], $SettingsByCategory['variation_image'], $SettingsByCategory['modification_image']))
            {
                foreach($WildberriesCardDTO->getMedia() as $media)
                {
                    $this->createMediaFile($ProductDTO, ProductPhoto::class, $media);
                }
            }


            /**
             * Название продукта
             *
             * @var ProductTransDTO $ProductTransDTO
             */
            foreach($ProductDTO->getTranslate() as $ProductTransDTO)
            {
                $ProductTransDTO->getName() ?: $ProductTransDTO->setName($title);

                if(empty($ProductTransDTO->getName()))
                {
                    $ProductTransDTO->setName($WildberriesCardDTO->getName());
                }

            }

            /**
             * Описание файла
             *
             * @var ProductProjectDescriptionDTO $ProductProjectDescriptionDTO
             */

            $ProductProjectDTO = $ProductDTO->getProject();

            foreach($ProductProjectDTO->getDescription() as $ProductProjectDescriptionDTO)
            {
                $ProductProjectDescriptionDTO->setPreview($WildberriesCardDTO->getDescription());
            }


            $Product = $this->ProductHandler->handle($ProductDTO);

            if(false === ($Product instanceof Product))
            {
                $this->logger->critical(
                    sprintf('wildberries-products: Ошибка при сохранении карточки %s', $WildberriesCardDTO->getArticle()),
                    [$Product, self::class.':'.__LINE__],
                );

                continue;
            }

            /** Сохраняем параметры упаковки */

            if(class_exists(BaksDevDeliveryTransportBundle::class))
            {
                foreach($package as $pack)
                {
                    $DeliveryPackageProductParameterDTO = new DeliveryPackageProductParameterDTO()
                        ->setProduct($Product->getId())
                        ->setOffer($pack['offer'] ? new ProductOfferConst($pack['offer']) : null)
                        ->setVariation($pack['variation'] ? new ProductVariationConst($pack['variation']) : null)
                        ->setModification($pack['modification'] ? new ProductOfferConst($pack['modification']) : null)
                        ->setWidth($WildberriesCardDTO->getWidth())
                        ->setHeight($WildberriesCardDTO->getHeight())
                        ->setLength($WildberriesCardDTO->getLength())
                        ->setPackage(1)
                        ->setWeight(new Kilogram(0.1));

                    $DeliveryPackageProductParameter = $this->DeliveryPackageProductParameterHandler->handle($DeliveryPackageProductParameterDTO);

                    if(false === ($DeliveryPackageProductParameter instanceof DeliveryPackageProductParameter))
                    {
                        $this->logger->critical(
                            sprintf('wildberries-products: Ошибка при сохранении параметров упаковки артикула %s', $WildberriesCardDTO->getArticle()),
                            [$DeliveryPackageProductParameter, $pack, self::class.':'.__LINE__],

                        );
                    }
                }
            }
        }
    }

    public function filterTitle(array $haystack, string $title): string
    {
        $haystack = array_map("mb_strtolower", $haystack);

        $title = mb_strtolower($title);
        $title = (string) str_ireplace($haystack, '', $title);
        $title = preg_replace('/\s/', ' ', $title);
        $title = trim($title);

        return mb_ucfirst($title);
    }

    public function createMediaFile(
        ProductOffersCollectionDTO|ProductVariationCollectionDTO|ProductModificationCollectionDTO|ProductDTO $parent,
        string $entity,
        string $mediaFile,
    ): void
    {

        $ref = new ReflectionClass($entity);
        /** @var ReflectionAttribute $table */
        $table = current($ref->getAttributes(Table::class));
        $table = $table->getArguments()['name'] ?: false;

        if(false === $table)
        {
            return;
        }


        $arrImage = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
        $mediaFileInfo = pathinfo($mediaFile);

        if(false === in_array($mediaFileInfo['extension'], $arrImage, true))
        {
            return;
        }

        $ImageCollectionDTO = match (true)
        {
            ($parent instanceof ProductDTO) => new PhotoCollectionDTO(),
            ($parent instanceof ProductOffersCollectionDTO) => new ProductOfferImageCollectionDTO(),
            ($parent instanceof ProductVariationCollectionDTO) => new ProductVariationImageCollectionDTO(),
            ($parent instanceof ProductModificationCollectionDTO) => new ProductModificationImageCollectionDTO(),
            default => false
        };

        if(false === $ImageCollectionDTO)
        {
            return;
        }


        /** Не загружаем, если в коллекции имеется такое изображение  */

        $isExistFile = $parent->getImage()->filter(function($element) use ($mediaFile) {
            return $element->getName() === md5($mediaFile);
        });

        if(false === $isExistFile->isEmpty())
        {
            return;
        }


        $this->WildberriesCardImage->get(
            $mediaFile,
            $ImageCollectionDTO,
            $table,
        );

        if($parent->getImage()->isEmpty() === true)
        {
            $ImageCollectionDTO->setRoot(true);
        }

        $parent->addImage($ImageCollectionDTO);
    }
}
