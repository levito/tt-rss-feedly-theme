<?php
interface IAuthModule {
	function authenticate($login, $password);
}
