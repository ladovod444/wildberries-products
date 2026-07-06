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

namespace BaksDev\Wildberries\Products\Api\Settings\Characteristic\Tests;

use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Products\Api\Settings\Characteristic\FindAllWbCharacteristicRequest;
use BaksDev\Wildberries\Products\Api\Settings\Characteristic\WbCharacteristicDTO;
use BaksDev\Wildberries\Products\Mapper\Params\WildberriesProductParametersCollection;
use BaksDev\Wildberries\Products\Mapper\Params\WildberriesProductParametersInterface;
use BaksDev\Wildberries\Products\Type\Settings\Property\WildberriesProductProperty;
use BaksDev\Wildberries\Type\Authorization\WbAuthorizationToken;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('wildberries-products')]
class WbCharacteristicRequestTest extends KernelTestCase
{
    private static WbAuthorizationToken $Authorization;

    public static function setUpBeforeClass(): void
    {
        /** @see .env.test */
        self::$Authorization = new WbAuthorizationToken(
            profile: new UserProfileUid($_SERVER['TEST_WILDBERRIES_PROFILE']),
            token: $_SERVER['TEST_WILDBERRIES_TOKEN'],
            warehouse: $_SERVER['TEST_WILDBERRIES_WAREHOUSE'] ?? null,
            percent: $_SERVER['TEST_WILDBERRIES_PERCENT'] ?? "0",
            card: $_SERVER['TEST_WILDBERRIES_CARD'] === "true" ?? false,
            stock: $_SERVER['TEST_WILDBERRIES_STOCK'] === "true" ?? false,
        );
    }

    public function testUseCase(): void
    {
        /** @var FindAllWbCharacteristicRequest $WbCharacteristicRequest */
        $WbCharacteristicRequest = self::getContainer()->get(FindAllWbCharacteristicRequest::class);
        $WbCharacteristicRequest->TokenHttpClient(self::$Authorization);

        $cats = [
            WildberriesProductProperty::CATEGORY_TIRE, // 5283 Шины автомобильные

            WildberriesProductProperty::CATEGORY_DESKS, // 7611 Столы письменные
            WildberriesProductProperty::CATEGORY_RACKS, // 1901 Стелажи

            WildberriesProductProperty::CATEGORY_SHIRTS, // 192 Футболки
            WildberriesProductProperty::CATEGORY_HOODIE, // 1724 Худи
            WildberriesProductProperty::CATEGORY_JEANS, // 180 Джинсы
            WildberriesProductProperty::CATEGORY_SVITSHOT, // 159 Свитшоты
            WildberriesProductProperty::CATEGORY_TOP, // 185 Топы
            WildberriesProductProperty::CATEGORY_KITCHEN_APRONS, // 402 Фартуки кухонные
            WildberriesProductProperty::CATEGORY_SLIPPERS, // 106 Тапки
            WildberriesProductProperty::CATEGORY_STRAPS,// 107 Шлепанцы;
            WildberriesProductProperty::CATEGORY_SABO, // 98 Cабо;
            WildberriesProductProperty::CATEGORY_SHIRTS_SPORT, // 5217 Футболка спортивная;
            WildberriesProductProperty::CATEGORY_CZECH, // 1586 Чешки;
            WildberriesProductProperty::CATEGORY_LONGSLEEVE, // 217 Лонгсливы;
        ];

        /** @see WildberriesProductProperty */
        //$cats = [WildberriesProductProperty::CATEGORY_RACKS];

        foreach($cats as $category)
        {
            $data = $WbCharacteristicRequest
                ->category($category)
                ->findAll();

            /** @var WildberriesProductParametersCollection $WildberriesProductParamsCollection */
            $WildberriesProductParamsCollection = self::getContainer()->get(WildberriesProductParametersCollection::class);

            $params = $WildberriesProductParamsCollection->cases($category);

            /** @var WbCharacteristicDTO $item */

            $count = 0;


            foreach($data as $item)
            {

                self::assertNotFalse($params,
                    sprintf('Отсутствует элемент ID = %s ( %s, %s ) для категории %s', $item->getId(), $item->getName(), $item->getUnit(), $category),
                );

                /** Проверяем по всем параметрам */

                self::assertNotEmpty(array_filter($params,
                    static function(WildberriesProductParametersInterface $param) use ($item) {
                        return $param->equals($item->getId());
                    }), sprintf('Отсутствует элемент ID = %s ( %s, %s ) для категории %s', $item->getId(), $item->getName(), $item->getUnit(), $category));


                ++$count;
            }


            //self::assertCount($count, $params, message: sprintf('В категории ID = %s количество элементов %s при %s параметрах', $category, ($i+1), count($params)));
        }

        self::assertTrue(true);

    }

}