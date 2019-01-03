function nsfwShow(elem) {
	let content = elem.parentNode.getElementsBySelector("div.nswf.content")[0];

	if (content) {
		Element.toggle(content);
	}
}
