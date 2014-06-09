<form class="test-feed-form">
	<p><label>URL: <input name="url" value="<?= !empty($url) ? $url : '' ?>" placeholder="e.g. waterpigs.co.uk" /></label> <button type="submit">Test</button></p>
</form>

<div class="x-scroll-wrapper test-columns">
	<?php if ($column !== null): ?>
	<div class="columns">
		<?= $render('column.html', ['column' => $column]) ?>

		<div class="column light-column test-cleansed-column y-scroll-wrapper">
			<?php foreach ($cleansed as $post): ?>
			<div class="properties">
				<ul>
					<?php foreach ($post as $key => $value): ?>
					<li><b><?= $key ?></b>: <?php if (is_array($value)): ?>
							<ul>
								<?php foreach ($value as $nKey => $nValue): ?>
								<li><b><?= $nKey ?></b>: <?= htmlspecialchars(json_encode($nValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></li>
								<?php endforeach ?>
							</ul>
						<?php else: ?>
							<?= htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>
						<?php endif ?></li>
					<?php endforeach ?>
				</ul>
			</div>
			<?php endforeach ?>
		</div>

		<div class="column double-width">
			<textarea class="test-html codemirror" data-codemirror-mode="text/html"><?= htmlspecialchars($html) ?></textarea>
			<textarea class="test-microformats codemirror" data-codemirror-mode="application/json"><?= htmlspecialchars(json_encode($mf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></textarea>
		</div>
	</div>
	<?php endif ?>
</div>
