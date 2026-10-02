<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;

/**
 * Document `array:a,b` as an object that allows only those keys, without requiring them.
 *
 * Scramble marks every listed key as required, but the rule only rejects other keys. A key is required
 * when its own rules say so, e.g. `schema.*.id => required`.
 */
class ArrayKeysRuleTransformer implements RuleTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->getRule() === 'array' && $rule->parameters !== [];
    }

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        $object = new ObjectType;

        foreach ($rule->parameters as $key) {
            $object->addProperty((string) $key, new UnknownType);
        }

        return $object;
    }
}
