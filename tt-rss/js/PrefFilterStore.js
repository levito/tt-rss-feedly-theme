define(["dojo/_base/declare", "dojo/data/ItemFileWriteStore"], function (declare) {

	return declare("fox.PrefFilterStore", dojo.data.ItemFileWriteStore, {

		_saveEverything: function (saveCompleteCallback, saveFailedCallback,
								   newFileContentString) {

			dojo.xhrPost({
				url: "backend.php",
				content: {
					op: "pref-filters", method: "savefilterorder",
					payload: newFileContentString
				},
				error: saveFailedCallback,
				load: saveCompleteCallback
			});
		},

	});
});


