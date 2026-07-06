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
 *
 */

declare(strict_types=1);

namespace BaksDev\Wildberries\Products\Repository\Cards\CurrentWildberriesProductsCard;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\DeliveryTransport\Entity\ProductParameter\DeliveryPackageProductParameter;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Description\ProductDescription;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Barcode\ProductOfferBarcode;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\Price\ProductOfferPrice;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Quantity\ProductOfferQuantity;
use BaksDev\Products\Product\Entity\Offers\Variation\Barcode\ProductVariationBarcode;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Barcode\ProductModificationBarcode;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Price\ProductModificationPrice;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Quantity\ProductModificationQuantity;
use BaksDev\Products\Product\Entity\Offers\Variation\Price\ProductVariationPrice;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Offers\Variation\Quantity\ProductVariationQuantity;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Price\ProductPrice;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\ProductInvariable;
use BaksDev\Products\Product\Entity\Property\ProductProperty;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\BaksDevProductsStocksBundle;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Products\Entity\Custom\Images\WildberriesProductCustomImage;
use BaksDev\Wildberries\Products\Entity\Custom\WildberriesProductCustom;
use BaksDev\Wildberries\Products\Entity\Settings\Invariable\WbProductSettingsInvariable;
use BaksDev\Wildberries\Products\Entity\Settings\Parameters\WbProductSettingsParameters;
use BaksDev\Wildberries\Products\Entity\Settings\Property\WbProductSettingsProperty;
use BaksDev\Wildberries\Products\Entity\Settings\WbProductSettings;
use InvalidArgumentException;


final class WildberriesProductsCardRepository implements WildberriesProductsCardInterface
{
    private int $limit = 100000;
    private UserProfileUid|false $profile = false;
    /**
     * ID продукта
     */
    private ProductUid|false $product = false;
    /**
     * Постоянный уникальный идентификатор ТП
     */
    private ProductOfferConst|false $offerConst = false;
    /**
     * Постоянный уникальный идентификатор варианта
     */
    private ProductVariationConst|false $variationConst = false;
    /**
     * Постоянный уникальный идентификатор модификации
     */
    private ProductModificationConst|false $modificationConst = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function setLimit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function forProfile(UserProfile|UserProfileUid|string $profile): self
    {
        if(empty($profile))
        {
            $this->profile = false;

            return $this;
        }

        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function forProduct(Product|ProductUid|string $product): self
    {
        if(empty($product))
        {
            $this->product = false;

            return $this;
        }

        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        if($product instanceof Product)
        {
            $product = $product->getId();
        }

        $this->product = $product;

        return $this;
    }

    public function forOfferConst(ProductOfferConst|string|null|false $offerConst): self
    {
        if(empty($offerConst))
        {
            $this->offerConst = false;
            return $this;
        }

        if(is_string($offerConst))
        {
            $offerConst = new ProductOfferConst($offerConst);
        }

        $this->offerConst = $offerConst;

        return $this;
    }

    public function forVariationConst(ProductVariationConst|string|null|false $variationConst): self
    {
        if(empty($variationConst))
        {
            $this->variationConst = false;
            return $this;
        }

        if(is_string($variationConst))
        {
            $variationConst = new ProductVariationConst($variationConst);
        }

        $this->variationConst = $variationConst;

        return $this;
    }

    public function forModificationConst(ProductModificationConst|string|null|false $modificationConst): self
    {
        if(empty($modificationConst))
        {
            $this->modificationConst = false;
            return $this;
        }

        if(is_string($modificationConst))
        {
            $modificationConst = new ProductModificationConst($modificationConst);
        }

        $this->modificationConst = $modificationConst;

        return $this;
    }

    public function find(): WildberriesProductsCardResult|false
    {
        if($this->product === false)
        {
            throw new InvalidArgumentException('Invalid Argument product');
        }

        if($this->profile === false)
        {
            throw new InvalidArgumentException('Invalid Argument profile');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('product.id AS product_uid')
            ->from(Product::class, 'product')
            ->where('product.id = :product')
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE,
            );


        /* ProductInfo */
        /** Передаем профиль, т.к. необходимо будет его использовать для обращения по апи */
        $dbal
            ->addSelect('product_info.article AS product_card')
            ->addSelect(':profile AS profile')
            ->join(
                'product',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id AND (product_info.profile IS NULL OR product_info.profile = :profile)',
            )
            ->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE,
            );


        /**
         * ProductOffer
         */

        if($this->offerConst instanceof ProductOfferConst)
        {
            $dbal
                ->addSelect('product_offer.const AS offer_const')
                ->addSelect('product_offer.value AS product_offer_value')
                ->addSelect('product_offer.postfix AS product_offer_postfix')
                ->leftJoin(
                    'product',
                    ProductOffer::class,
                    'product_offer',
                    'product_offer.event = product.event AND
                               product_offer.const = :offer_const',
                )
                ->setParameter(
                    key: 'offer_const',
                    value: $this->offerConst,
                    type: ProductOfferConst::TYPE,
                );

        }
        else
        {
            $dbal
                ->addSelect('NULL AS offer_const')
                ->addSelect('NULL AS product_offer_value')
                ->addSelect('NULL AS product_offer_postfix')
                ->leftJoin(
                    'product',
                    ProductOffer::class,
                    'product_offer',
                    'product_offer.event = product.event',
                );
        }

        $dbal
            ->leftJoin(
                'product_offer',
                ProductOfferBarcode::class,
                'product_offer_barcode',
                'product_offer_barcode.offer = product_offer.id',
            );

        /**
         * ProductVariation
         */

        if($this->variationConst instanceof ProductVariationConst)
        {
            $dbal
                ->addSelect('product_variation.const AS variation_const')
                ->addSelect('product_variation.value AS product_variation_value')
                ->addSelect('product_variation.postfix AS product_variation_postfix')
                ->leftJoin(
                    'product_offer',
                    ProductVariation::class,
                    'product_variation',
                    'product_variation.offer = product_offer.id AND product_variation.const = :variation_const',
                )
                ->setParameter(
                    key: 'variation_const',
                    value: $this->variationConst,
                    type: ProductVariationConst::TYPE,
                );
        }
        else
        {
            $dbal
                ->addSelect('NULL AS variation_const')
                ->addSelect('NULL AS product_variation_value')
                ->addSelect('NULL AS product_variation_postfix')
                ->leftJoin(
                    'product_offer',
                    ProductVariation::class,
                    'product_variation',
                    'product_variation.offer = product_offer.id',
                );
        }

        $dbal
            ->leftJoin(
                'product_variation',
                ProductVariationBarcode::class,
                'product_variation_barcode',
                'product_variation_barcode.variation = product_variation.id',
            );

        /**
         * ProductModification
         */

        if($this->modificationConst instanceof ProductModificationConst)
        {
            $dbal
                ->addSelect('product_modification.const AS modification_const')
                ->addSelect('product_modification.value AS product_modification_value')
                ->addSelect('product_modification.postfix AS product_modification_postfix')
                ->leftJoin(
                    'product_variation',
                    ProductModification::class,
                    'product_modification',
                    'product_modification.variation = product_variation.id AND product_modification.const = :modification_const',
                )
                ->setParameter(
                    key: 'modification_const',
                    value: $this->modificationConst,
                    type: ProductModificationConst::TYPE,
                );
        }
        else
        {
            $dbal
                ->addSelect('NULL AS modification_const')
                ->addSelect('NULL AS product_modification_value')
                ->addSelect('NULL AS product_modification_postfix')
                ->leftJoin(
                    'product_variation',
                    ProductModification::class,
                    'product_modification',
                    'product_modification.variation = product_variation.id',
                );
        }


        $dbal
            ->leftJoin(
                'product_modification',
                ProductModificationBarcode::class,
                'product_modification_barcode',
                'product_modification_barcode.modification = product_modification.id',
            );


        if($this->offerConst instanceof ProductOfferConst)
        {

            /** Штрихкоды продукта */

            $dbal->addSelect(
                "
            JSON_AGG
                    (DISTINCT
         			CASE
         			    WHEN product_modification_barcode.value IS NOT NULL
                        THEN product_modification_barcode.value
                        
                        WHEN product_variation_barcode.value IS NOT NULL
                        THEN product_variation_barcode.value
                        
                        WHEN product_offer_barcode.value IS NOT NULL
                        THEN product_offer_barcode.value
                        
                        WHEN product_info.barcode IS NOT NULL
                        THEN product_info.barcode
                        
                        ELSE NULL
                    END
                    )
                    AS barcodes",
            );

            $dbal->addSelect(
                "JSON_AGG
                ( DISTINCT

                    JSONB_BUILD_OBJECT(

                        'value', COALESCE(
                            product_modification.value,
                            product_variation.value,
                            product_offer.value
                        ),
                        
                        'barcode', COALESCE(
                            product_modification.barcode_old,
                            product_variation.barcode_old,
                            product_offer.barcode_old,
                            product_info.barcode
                        ),

                        'price', COALESCE(
                            NULLIF(product_modification_price.price, 0),
                            NULLIF(product_variation_price.price, 0),
                            NULLIF(product_offer_price.price, 0),
                            NULLIF(product_price.price, 0),
                            0
                        )
                    )
                ) AS product_size",
            );
        }
        else
        {
            $dbal->addSelect('NULL AS product_size');
        }

        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product.event',
            );


        $dbal
            ->addSelect('product_desc.preview AS product_preview')
            ->leftJoin(
                'product',
                ProductDescription::class,
                'product_desc',
                'product_desc.event = product.event AND product_desc.device = :device ',
            )->setParameter('device', 'pc');


        /* Категория */
        $dbal->leftJoin(
            'product',
            ProductCategory::class,
            'product_category',
            'product_category.event = product.event AND product_category.root = true',
        );

        $dbal->join(
            'product_category',
            CategoryProduct::class,
            'category',
            'category.id = product_category.category',
        );

        $dbal
            ->addSelect('category_trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryProductTrans::class,
                'category_trans',
                'category_trans.event = category.event AND category_trans.local = :local',
            );

        $dbal
            ->addSelect('MAX(product_package.length) AS length') // Длина упаковки в см.
            ->addSelect('MAX(product_package.width) AS width') // Ширина упаковки в см.
            ->addSelect('MAX(product_package.height) AS height') // Высота упаковки в см.
            ->addSelect('MAX(product_package.weight) AS weight') // Вес товара в кг с учетом упаковки (брутто).
            ->leftJoin(
                'product_variation',
                DeliveryPackageProductParameter::class,
                'product_package',
                'product_package.product = product.id

                    AND

                    (
                        (product_offer.const IS NOT NULL AND product_package.offer = product_offer.const) OR
                        (product_offer.const IS NULL AND product_package.offer IS NULL)
                    )

                    AND

                    (
                        (product_variation.const IS NOT NULL AND product_package.variation = product_variation.const) OR
                        (product_variation.const IS NULL AND product_package.variation IS NULL)
                    )

                   AND

                   (
                        (product_modification.const IS NOT NULL AND product_package.modification = product_modification.const) OR
                        (product_modification.const IS NULL AND product_package.modification IS NULL)
                   )
                ',
            );

        $dbal
            ->join(
                'product_category',
                WbProductSettings::class,
                'settings',
                'settings.id = product_category.category',
            );

        /**
         * Категория, согласно настройкам соотношений
         */
        $dbal
            ->addSelect('settings_invariable.category AS market_category')
            ->leftJoin(
                'settings',
                WbProductSettingsInvariable::class,
                'settings_invariable',
                'settings_invariable.main = settings.id',
            );


        /**
         * Свойства по умолчанию
         */
        $dbal
            ->leftJoin(
                'settings',
                WbProductSettingsProperty::class,
                'settings_property',
                'settings_property.event = settings.event',
            );


        // Получаем значение из свойств товара
        $dbal
            ->leftJoin(
                'settings_property',
                ProductProperty::class,
                'product_property',
                'product_property.event = product.event AND product_property.field = settings_property.field',
            );

        $dbal->addSelect(
            "JSON_AGG
			( DISTINCT

					JSONB_BUILD_OBJECT
					(
						'type', settings_property.type,

						'value', CASE
						   WHEN settings_property.def IS NOT NULL THEN settings_property.def
						   WHEN product_property.value IS NOT NULL THEN product_property.value
						   ELSE NULL
						END
					)
			)
			AS product_property",
        );


        /**
         * Параметры
         */
        $dbal
            ->leftJoin(
                'settings',
                WbProductSettingsParameters::class,
                'settings_params',
                'settings_params.event = settings.event',
            );


        // Получаем значение из свойств товара
        $dbal
            ->leftJoin(
                'settings_params',
                ProductProperty::class,
                'product_property_params',
                '
                product_property_params.event = product.event AND
                product_property_params.field = settings_params.field
            ');


        // Получаем значение из модификации множественного варианта
        $dbal
            ->leftJoin(
                'settings_params',
                ProductOffer::class,
                'product_offer_params',
                '
                    product_offer_params.id = product_offer.id AND
                    product_offer_params.category_offer = settings_params.field
            ',
            );

        $dbal
            ->leftJoin(
                'settings_params',
                ProductVariation::class,
                'product_variation_params',
                '
                    product_variation_params.id = product_variation.id AND
                    product_variation_params.category_variation = settings_params.field
           ',
            );

        $dbal
            ->leftJoin(
                'settings_params',
                ProductModification::class,
                'product_modification_params',
                '
                    product_modification_params.id = product_modification.id AND
                    product_modification_params.category_modification = settings_params.field
            ');

        $dbal->addSelect(
            "JSON_AGG
			( DISTINCT
					JSONB_BUILD_OBJECT
					(
						'name', settings_params.type,

						'value', CASE
						   WHEN product_property_params.value IS NOT NULL THEN product_property_params.value
						   WHEN product_modification_params.value IS NOT NULL THEN product_modification_params.value
						   WHEN product_variation_params.value IS NOT NULL THEN product_variation_params.value
						   WHEN product_offer_params.value IS NOT NULL THEN product_offer_params.value
						   ELSE NULL
						END
					)
			)
			AS product_params",
        );

        /**
         * Product Invariable
         */
        $dbal->leftJoin(
            'product_modification',
            ProductInvariable::class,
            'product_invariable',
            '
                   product_invariable.product = product.id AND
                   (
                       (product_offer.const IS NOT NULL AND product_invariable.offer = product_offer.const) OR
                       (product_offer.const IS NULL AND product_invariable.offer IS NULL)
                   )
                   AND
                   (
                       (product_variation.const IS NOT NULL AND product_invariable.variation = product_variation.const) OR
                       (product_variation.const IS NULL AND product_invariable.variation IS NULL)
                   )
                  AND
                  (
                       (product_modification.const IS NOT NULL AND product_invariable.modification = product_modification.const) OR
                       (product_modification.const IS NULL AND product_invariable.modification IS NULL)
                  )
           ');


        /**
         * Фото продукции
         */

        /** Кастомные настройки продукта Яндекс Маркет  */

        $dbal
            ->leftJoin(
                'product_invariable',
                WildberriesProductCustom::class,
                'wb_product_custom',
                'wb_product_custom.invariable = product_invariable.id',
            );


        /* Кастомные фото Яндекс Маркет */
        $dbal->leftJoin(
            'wb_product_custom',
            WildberriesProductCustomImage::class,
            'wb_product_custom_images',
            '
                wb_product_custom_images.invariable = product_invariable.id
            ',
        );


        /* Фото модификаций */

        $dbal->leftJoin(
            'product_modification',
            ProductModificationImage::class,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id',
        );


        /* Фото вариантов */

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::class,
            'product_variation_image',
            'product_variation_image.variation = product_variation.id',
        );


        /* Фот торговых предложений */

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            'product_offer_images.offer = product_offer.id',
        );

        /* Фото продукта */

        $dbal->leftJoin(
            'product',
            ProductPhoto::class,
            'product_photo',
            'product_photo.event = product.event',
        );

        $dbal->addSelect(
            "JSON_AGG
		    ( DISTINCT
				CASE 
				
				WHEN product_offer_images.ext IS NOT NULL 
				THEN JSONB_BUILD_OBJECT
					(
						'product_img_root', product_offer_images.root,
						'product_img', CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_images.name),
						'product_img_ext', product_offer_images.ext,
						'product_img_cdn', product_offer_images.cdn
					) 
					
				WHEN product_variation_image.ext IS NOT NULL 
				THEN JSONB_BUILD_OBJECT
					(
						'product_img_root', product_variation_image.root,
						'product_img', CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_variation_image.name),
						'product_img_ext', product_variation_image.ext,
						'product_img_cdn', product_variation_image.cdn
					)	
					
					
				WHEN product_modification_image.ext IS NOT NULL 
				THEN JSONB_BUILD_OBJECT
					(
						'product_img_root', product_modification_image.root,
						'product_img', CONCAT ( '/upload/".$dbal->table(ProductModificationImage::class)."' , '/', product_modification_image.name),
						'product_img_ext', product_modification_image.ext,
						'product_img_cdn', product_modification_image.cdn
					)
					
				WHEN product_photo.ext IS NOT NULL 
				THEN JSONB_BUILD_OBJECT
					(
						'product_img_root', product_photo.root,
						'product_img', CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name),
						'product_img_ext', product_photo.ext,
						'product_img_cdn', product_photo.cdn
					)

				END
	 
			) AS product_images",
        );


        /* Базовая Цена товара */
        $dbal->leftJoin(
            'product',
            ProductPrice::class,
            'product_price',
            'product_price.event = product.event',
        );

        /* Цена торгового предложения */
        $dbal->leftJoin(
            'product_offer',
            ProductOfferPrice::class,
            'product_offer_price',
            'product_offer_price.offer = product_offer.id',
        );

        /* Цена множественного варианта */
        $dbal->leftJoin(
            'product_variation',
            ProductVariationPrice::class,
            'product_variation_price',
            'product_variation_price.variation = product_variation.id',
        );


        /* Цена модификации множественного варианта */
        $dbal->leftJoin(
            'product_modification',
            ProductModificationPrice::class,
            'product_modification_price',
            'product_modification_price.modification = product_modification.id',
        );


        /* Стоимость продукта */

        $dbal->addSelect('
			COALESCE(
                NULLIF(product_modification_price.price, 0), 
                NULLIF(product_variation_price.price, 0), 
                NULLIF(product_offer_price.price, 0), 
                NULLIF(product_price.price, 0),
                0
            ) AS product_price
		');

        /* Предыдущая стоимость продукта */

        $dbal->addSelect('
			COALESCE(
                NULLIF(product_modification_price.old, 0),
                NULLIF(product_variation_price.old, 0),
                NULLIF(product_offer_price.old, 0),
                NULLIF(product_price.old, 0),
                0
            ) AS product_old_price
		');

        /* Валюта продукта */

        $dbal->addSelect(
            '
			COALESCE(
                CASE WHEN product_modification_price.price IS NOT NULL AND product_modification_price.price > 0 
                     THEN product_modification_price.currency END, 
                     
                CASE WHEN product_variation_price.price IS NOT NULL AND product_variation_price.price > 0 
                     THEN product_variation_price.currency END, 
                     
                CASE WHEN product_offer_price.price IS NOT NULL AND product_offer_price.price > 0 
                     THEN product_offer_price.currency END, 
                     
                CASE WHEN product_price.price IS NOT NULL AND product_price.price > 0 
                     THEN product_price.currency END
            ) AS product_currency
		',
        );


        /**
         * Наличие продукции на складе
         * Если подключен модуль складского учета и передан идентификатор профиля
         */

        if(true === ($this->profile instanceof UserProfileUid) && class_exists(BaksDevProductsStocksBundle::class))
        {

            $dbal
                ->addSelect("JSON_AGG ( 
                        DISTINCT JSONB_BUILD_OBJECT (
                            'total', stock.total, 
                            'reserve', stock.reserve 
                        )) FILTER (WHERE stock.total > stock.reserve)
            
                        AS product_quantity",
                )
                ->leftJoin(
                    'product_modification',
                    ProductStockTotal::class,
                    'stock',
                    '
                    stock.profile = :profile AND
                    stock.product = product.id 
                    
                    AND
                        
                        CASE 
                            WHEN product_offer.const IS NOT NULL 
                            THEN stock.offer = product_offer.const
                            ELSE stock.offer IS NULL
                        END
                            
                    AND 
                    
                        CASE
                            WHEN product_variation.const IS NOT NULL 
                            THEN stock.variation = product_variation.const
                            ELSE stock.variation IS NULL
                        END
                        
                    AND
                    
                        CASE
                            WHEN product_modification.const IS NOT NULL 
                            THEN stock.modification = product_modification.const
                            ELSE stock.modification IS NULL
                        END
                    
                    
                ',
                )
                ->setParameter(
                    'profile',
                    $this->profile,
                    UserProfileUid::TYPE,
                );

        }

        /**
         * Наличие продукции в карточке
         */

        else
        {

            /* Наличие и резерв торгового предложения */
            $dbal->leftJoin(
                'product_offer',
                ProductOfferQuantity::class,
                'product_offer_quantity',
                'product_offer_quantity.offer = product_offer.id',
            );

            /* Наличие и резерв множественного варианта */
            $dbal->leftJoin(
                'product_variation',
                ProductVariationQuantity::class,
                'product_variation_quantity',
                'product_variation_quantity.variation = product_variation.id',
            );

            /* Наличие и резерв модификации множественного варианта */
            $dbal->leftJoin(
                'product_modification',
                ProductModificationQuantity::class,
                'product_modification_quantity',
                'product_modification_quantity.modification = product_modification.id',
            );

            $dbal
                ->addSelect("JSON_AGG (
                        DISTINCT JSONB_BUILD_OBJECT (
                            
                            
                            'total', COALESCE(
                                            product_modification_quantity.quantity, 
                                            product_variation_quantity.quantity, 
                                            product_offer_quantity.quantity, 
                                            product_price.quantity,
                                            0
                                        ), 
                            
                            
                            'reserve', COALESCE(
                                            product_modification_quantity.reserve, 
                                            product_variation_quantity.reserve, 
                                            product_offer_quantity.reserve, 
                                            product_price.reserve,
                                            0
                                        )
                        ) )
            
                        AS product_quantity",
                );

        }


        //        /* Наличие и резерв торгового предложения */
        //        $dbal->leftJoin(
        //            'product_offer',
        //            ProductOfferQuantity::class,
        //            'product_offer_quantity',
        //            'product_offer_quantity.offer = product_offer.id',
        //        );
        //
        //        /* Наличие и резерв множественного варианта */
        //        $dbal->leftJoin(
        //            'product_variation',
        //            ProductVariationQuantity::class,
        //            'product_variation_quantity',
        //            'product_variation_quantity.variation = product_variation.id',
        //        );
        //
        //        /* Наличие и резерв модификации множественного варианта */
        //        $dbal->leftJoin(
        //            'product_modification',
        //            ProductModificationQuantity::class,
        //            'product_modification_quantity',
        //            'product_modification_quantity.modification = product_modification.id',
        //        );
        //
        //        /* Наличие продукта за вычетом резерва  */
        //        $dbal->addSelect('
        //            COALESCE(
        //                CASE WHEN product_modification_quantity.quantity > 0 AND product_modification_quantity.quantity > product_modification_quantity.reserve
        //                     THEN product_modification_quantity.quantity - ABS(product_modification_quantity.reserve) END,
        //                CASE WHEN product_variation_quantity.quantity > 0 AND product_variation_quantity.quantity > product_variation_quantity.reserve
        //                     THEN product_variation_quantity.quantity - ABS(product_variation_quantity.reserve) END,
        //                CASE WHEN product_offer_quantity.quantity > 0 AND product_offer_quantity.quantity > product_offer_quantity.reserve
        //                     THEN product_offer_quantity.quantity - ABS(product_offer_quantity.reserve) END,
        //                CASE WHEN product_price.quantity > 0 AND product_price.quantity > product_price.reserve
        //                     THEN product_price.quantity - ABS(product_price.reserve) END
        //            ) AS product_quantity
        //		');


        $dbal->addSelect('
            JSON_AGG
            ( DISTINCT
                COALESCE(
                    product_modification.article,
                    product_variation.article,
                    product_offer.article,
                    product_info.article
                )
            ) AS article
		');

        $dbal->addSelect('product_info.article AS card_article');

        $dbal->allGroupByExclude();

        //$dbal->setMaxResults($this->limit);

        return $dbal
            ->enableCache('products-product', '5 seconds')
            ->fetchHydrate(WildberriesProductsCardResult::class);
    }
}
