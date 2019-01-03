define(["dojo/_base/declare", "dojo/dom-construct", "dijit/Tree"], function (declare, domConstruct) {

	return declare("lib._CheckBoxTreeNode", dijit._TreeNode,
		{
			// _checkbox: [protected] dojo.doc.element
			//		Local reference to the dojo.doc.element of type 'checkbox'
			_checkbox: null,

			_createCheckbox: function () {
				// summary:
				//		Create a checkbox on the CheckBoxTreeNode
				// description:
				//		Create a checkbox on the CheckBoxTreeNode. The checkbox is ONLY created if a
				//		valid reference was found in the dojo.data store or the attribute 'checkboxAll'
				//		is set to true. If the current state is 'undefined' no reference was found and
				//		'checkboxAll' is set to false.
				//		Note: the attribute 'checkboxAll' is validated by the getCheckboxState function
				//		therefore no need to do that here. (see getCheckboxState for details).
				//
				var currState = this.tree.model.getCheckboxState(this.item);
				if (currState !== undefined) {
					this._checkbox = new dijit.form.CheckBox();
					//this._checkbox = dojo.doc.createElement('input');
					this._checkbox.type = 'checkbox';
					this._checkbox.attr('checked', currState);
					domConstruct.place(this._checkbox.domNode, this.expandoNode, 'after');
				}
			},

			postCreate: function () {
				// summary:
				//		Handle the creation of the checkbox after the CheckBoxTreeNode has been instanciated.
				// description:
				//		Handle the creation of the checkbox after the CheckBoxTreeNode has been instanciated.
				this._createCheckbox();
				this.inherited(arguments);
			}

		});
});


