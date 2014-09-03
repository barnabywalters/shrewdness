<div class="header">
	<span>Shrewdness</span>

	<div class="user">
		<?= $token['me'] ?>

		<?php if (!empty($token['micropub_endpoint'])): ?>
		<abbr class="micropub-support" title="Micropub Support Enabled">MP</abbr>
		<?php endif ?>

		<form class="logout-form inline-form" action="<?= $logoutUrl ?>" method="post">
			<button type="submit">Log Out</button>
		</form>
	</div>
</div>

<div class="x-scroll-wrapper">
	<div class="columns">
		<?php foreach ($columns as $column): ?>
			<?= $render('column.html', ['column' => $column, 'token' => $token]) ?>
		<?php endforeach ?>

		<?= $render('new-column.html') ?>
	</div>
</div>
