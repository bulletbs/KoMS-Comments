<? $lang = function ($index) {
	return __(Kohana::message('comments', $index));
}; ?>

<div class="comments-form">
	<h2><?= $lang('title') ?></h2>
    <?= $errors ?>
    <a name="comment-form"></a>
	<?= Form::open(Request::$current->url().'#comment-form') ?>
		<? if ($show_username_field): ?>
			<?= Form::label('username', $lang('fields.username') . ':') ?>
			<?= Form::input('comment-username', $comment->username, array('class' => 'text')) ?>
            <div class="clear"></div>
		<? endif; ?>
		<?= Form::label('content', $lang('fields.content') . ':') ?>
		<?= Form::textarea('comment-content', $comment->content) ?>
        <?if(!Auth::instance()->logged_in()): ?>
            <div class="clear"></div><br>
            <?= Form::label('',' ')?>
            <?php echo Captcha::instance() ?>
            <div class="clear"></div>
            <?= Form::label('captcha', $lang('fields.captcha') . ':') ?>
            <?php echo Form::input('captcha')?>
        <?endif?>
        <?if($show_rating_field): ?>
            <div class="clear"></div>
            <?= Form::label('rating', $lang('fields.rating') . ':') ?>
            <?php echo Form::select('rating', Comments::ratingOptions())?>
        <?endif?>
		<?= Form::submit(null, $lang('submit'), array('class' => 'submit')) ?>
	<?= Form::close() ?>
</div>