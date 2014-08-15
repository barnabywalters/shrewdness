<form class="indieauth-form" action="/login/" method="post">
	<input type="hidden" name="next" value="<?= $nextUrl ?>" />
	<p><input class="indieauth-url" name="me" placeholder="e.g. yourdomain.com" required /> <button class="indieauth-submit" type="submit">Log In</button></p>
</form>
