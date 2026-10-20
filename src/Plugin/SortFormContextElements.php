<?php

namespace Trinos\PostcodeNL\Plugin;

use Hyva\Checkout\Model\Form\AbstractEntityForm;

class SortFormContextElements
{
    /**
     * SortFormFieldsMapping makes the fields get created in alphabetical order. Since Hyvä Checkout 1.3.15
     * the fields live in context()->elements(), which AbstractEntityForm::sort() no longer sorts.
     * On 1.3.14 and older context() does not exist and sort() already covers all fields, so this is a no-op.
     *
     * @param AbstractEntityForm $subject
     * @param AbstractEntityForm $result
     * @return AbstractEntityForm
     */
    public function afterSort(AbstractEntityForm $subject, AbstractEntityForm $result): AbstractEntityForm
    {
        if (method_exists($result, 'context')) {
            $result->context()->elements()->sort();
        }

        return $result;
    }
}
