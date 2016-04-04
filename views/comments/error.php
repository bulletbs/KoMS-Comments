<div class="comments-errors">
	<b><?= $message ?></b>
	<ul>
        <?foreach($errors as $k=>$err):?>
            <?if($k == '_external' && is_array($err)):?>
                <?foreach($err as $er):?>
                    <li><?= $er ?></li>
                <?endforeach;?>
            <?else:?>
                <li><?echo $err;?></li>
            <?endif?>
        <?endforeach;?>
	</ul>
</div>