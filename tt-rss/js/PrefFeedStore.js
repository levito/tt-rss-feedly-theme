define(["dojo/_base/declare", "dojo/data/ItemFileWriteStore"], function (declare) {

	return declare("fox.PrefFeedStore", dojo.data.ItemFileWriteStore, {

		_saveEverything: function(saveCompleteCallback, saveFailedCallback,
								  newFileContentString) {

			dojo.xhrPost({
				url: "backend.php",
				content: {op: "pref-feeds", method: "savefeedorder",
					payload: newFileContentString},
				error: saveFailedCallback,
				load: saveCompleteCallback});
		},

	});

});


