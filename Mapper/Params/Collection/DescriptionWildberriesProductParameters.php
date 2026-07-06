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

declare(strict_types=1);

namespace BaksDev\Wildberries\Products\Mapper\Params\Collection;

use BaksDev\Wildberries\Products\Mapper\Params\WildberriesProductParametersInterface;
use BaksDev\Wildberries\Products\Repository\Cards\CurrentWildberriesProductsCard\WildberriesProductsCardResult;
use BaksDev\Wildberries\Products\Type\Settings\Property\WildberriesProductProperty;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('baks.wb.product.params')]
final class DescriptionWildberriesProductParameters implements WildberriesProductParametersInterface
{
    public const array CATEGORY = [
        WildberriesProductProperty::CATEGORY_TIRE,
        WildberriesProductProperty::CATEGORY_DESKS,
        WildberriesProductProperty::CATEGORY_RACKS,
        WildberriesProductProperty::CATEGORY_SHIRTS,
        WildberriesProductProperty::CATEGORY_SHIRTS_SPORT,
        WildberriesProductProperty::CATEGORY_HOODIE,
        WildberriesProductProperty::CATEGORY_JEANS,
        WildberriesProductProperty::CATEGORY_SVITSHOT,
        WildberriesProductProperty::CATEGORY_TOP,
        WildberriesProductProperty::CATEGORY_KITCHEN_APRONS,
        WildberriesProductProperty::CATEGORY_WORKERS_APRONS,
        WildberriesProductProperty::CATEGORY_SLIPPERS,
        WildberriesProductProperty::CATEGORY_STRAPS,
        WildberriesProductProperty::CATEGORY_SABO,
        WildberriesProductProperty::CATEGORY_CZECH,
        WildberriesProductProperty::CATEGORY_LONGSLEEVE,

    ];

    public const int ID = 14177452;

    /**
     * Сортировка (чем меньше число - тем первым в итерации будет значение)
     */
    public static function priority(): int
    {
        return 100;
    }

    public function required(): bool
    {
        return false;
    }

    public function default(): ?string
    {
        return null;
    }

    /** Массив допустимых значений */
    public function choices(): ?array
    {
        return null;
    }

    public function isSetting(): bool
    {
        return true;
    }

    public function getData(WildberriesProductsCardResult $data, ?TranslatorInterface $translator = null): ?array
    {
        $product_params = $data->getProductParams();

        if(false !== $data->getProductParams())
        {
            foreach($product_params as $product_param)
            {
                if($this->equals($product_param->name))
                {
                    return [
                        'id' => $this::ID,
                        'name' => $this->getName(),
                        'value' => $product_param->value,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Проверяет, относится ли значение к данному объекту
     */
    public function equals(int|string $param): bool
    {
        $param = mb_strtolower((string) $param);

        return in_array($param, [
            (string) self::ID,
            mb_strtolower($this->getName()),
        ], true);
    }

    public function getName(): string
    {
        return 'Описание';
    }

}
