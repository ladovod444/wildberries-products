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

namespace BaksDev\Wildberries\Products\Api\Settings\Characteristic;

use Symfony\Component\Validator\Constraints as Assert;


final class WbCharacteristicDTO
{

    /** Идентификатор характеристики */
    #[Assert\Uuid]
    private int $id;

    /** Название характеристики */
    private string $name;

    /** Обязательное */
    private bool $required;

    /** Единица измерения */
    private ?string $unit;

    public function __construct(array $data)
    {
        $this->id = $data['charcID'];
        $this->name = $data['name'];
        $this->required = $data['required'];
        $this->unit = $data['unitName'];
    }

    /**
     * Id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Required
     */
    public function isRequired(): bool
    {
        return $this->required;
    }


    /** Единица измерения */
    public function getUnit(): ?string
    {
        return $this->unit;
    }

}