<?php
interface IHandler {
	function csrf_ignore($method);
	function before($method);
	function after();
}
