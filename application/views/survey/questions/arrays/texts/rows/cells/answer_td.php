<?php
/**
 * Answer cell
 *
 * @var $ld
 * @var $myfname2
 * @var $labelText $labelans[$thiskey]
 * @var $kpclass
 * @var $maxlength
 * @var $inputwidth
 * @var $value
 */
?>

<!-- answer_td -->
<td class="answer_cell_<?php echo $ld;?> answer-item text-item">
    <label class="sr-only" for="answer<?php echo $myfname2; ?>">
        <?php echo $labelText;?>
    </label>
    <input
        type="text"
        name="<?php echo $myfname2; ?>"
        id="answer<?php echo $myfname2; ?>"
        class="form-control <?php echo $kpclass; ?>"
        <?php echo $maxlength; ?>
        size="<?php echo $inputwidth; ?>"
        value="<?php echo $value;?>"
    />
    <input
        type="hidden"
        name="java<?php echo $myfname2;?>"
        id="java<?php echo $myfname2; ?>"
    />
</td>
<!-- end of answer_td -->
