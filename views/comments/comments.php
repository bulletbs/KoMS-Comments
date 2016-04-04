
<div class="line_title">
    <h2>Комментарии</h2>
    <div class="clear"></div>
</div>
<?if(count($comments)):?>
<ul class="comments">
<? foreach ($comments as $comment): ?><li>
<a name="comment<?=$comment->id?>"></a>
<img src="<?= $comment->get_author_avatar();?>" alt="">
<div>
<b><?= $comment->get_author_name();?></b> <i>(<?= $comment->created ?>)</i>
<span><?= nl2br($comment->content) ?></span>
</div>
<div class="clear"></div>
</li><? endforeach; ?>
</ul>
<?else:?>
    <div class="comments-message">Еще никто не оставлял комментариев к данному материалу.</div>
<?endif?>