<?php
abstract class Af_ComicFilter {
	public abstract function supported();
	public abstract function process(&$article);
}