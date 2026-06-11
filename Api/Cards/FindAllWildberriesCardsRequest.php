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

namespace BaksDev\Wildberries\Products\Api\Cards;

use BaksDev\Wildberries\Api\Wildberries;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;

final class FindAllWildberriesCardsRequest extends Wildberries
{
    private const int LIMIT = 100;

    private ?DateTimeImmutable $updated = null;

    private ?int $nomenclature = null;

    private int $photo = -1;

    public function onlyPhoto(): self
    {
        $this->photo = 1;
        return $this;
    }

    public function onlyNoPhoto(): self
    {
        $this->photo = 0;
        return $this;
    }

    public function allPhoto(): self
    {
        $this->photo = -1;
        return $this;
    }


    /**
     * @see https://dev.wildberries.ru/openapi/work-with-products/#tag/Kartochki-tovarov/paths/~1content~1v2~1get~1cards~1list/post
     * @return Generator<WildberriesCardDTO>|false
     */
    public function findAll(int|string|null|false $search = null): Generator|false
    {
        while(true)
        {
            $cache = $this->getCacheInit('wildberries-products');
            $key = md5(self::class.$this->getTokenIdentifier().$this->nomenclature.$search);

            $content = $cache->get($key, function(ItemInterface $item) use ($search): array|false {

                $item->expiresAfter(DateInterval::createFromDateString('1 seconds'));

                $json = [
                    "settings" => [
                        "cursor" => [
                            "limit" => self::LIMIT,
                            "updatedAt" => $this->updated?->format(DateTimeInterface::W3C),
                            "nmID" => $this->nomenclature,

                        ],
                        "filter" => [
                            "textSearch" => $search ? (string) $search : '',
                            "withPhoto" => $this->photo,
                        ],
                    ],
                ];

                $response = $this->content()->TokenHttpClient()
                    ->request(
                        'POST',
                        '/content/v2/get/cards/list',
                        ['json' => $json],
                    );

                $content = $response->toArray(false);

                if($response->getStatusCode() !== 200)
                {
                    $this->logger->critical(
                        sprintf('wildberries: Ошибка %s списка карточек ', $response->getStatusCode()),
                        [$content, self::class.':'.__LINE__],
                    );

                    return false;
                }

                if(empty($content) || empty($content['cards']))
                {
                    return false;
                }

                $item->expiresAfter(DateInterval::createFromDateString('1 minutes'));

                return $response->toArray(false);
            });

            if(false === $content)
            {
                $cache->deleteItem($key);
                return false;
            }

            if(empty($content['cursor']))
            {
                break;
            }

            $cursor = $content['cursor'];

            foreach($content['cards'] as $data)
            {
                yield new WildberriesCardDTO($data, $this->getProfile());
            }

            if(self::LIMIT > $cursor['total'])
            {
                break;
            }

            $this->updated = new DateTimeImmutable($cursor['updatedAt']);
            $this->nomenclature = $cursor['nmID'];
        }
    }
}