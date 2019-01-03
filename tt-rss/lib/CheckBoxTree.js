define(["dojo/_base/declare", "dijit/Tree", "lib/_CheckBoxTreeNode" ], function (declare) {

	return declare( "lib.CheckBoxTree", dijit.Tree,
		{

			onNodeChecked: function(/*dojo.data.Item*/ storeItem, /*treeNode*/ treeNode) {
				// summary:
				//		Callback when a checkbox tree node is checked
				// tags:
				//		callback
			},

			onNodeUnchecked: function(/*dojo.data.Item*/ storeItem, /* treeNode */ treeNode) {
				// summary:
				//		Callback when a checkbox tree node is unchecked
				// tags:
				//		callback
			},

			_onClick: function(/*TreeNode*/ nodeWidget, /*Event*/ e) {
				// summary:
				//		Translates click events into commands for the controller to process
				// description:
				//		the _onClick function is called whenever a 'click' is detected. This
				//		instance of _onClick only handles the click events associated with
				//		the checkbox whos DOM name is INPUT.
				//
				var domElement = e.target;

				// Only handle checkbox clicks here
				if(domElement.type != 'checkbox') {
					return this.inherited( arguments );
				}

				this._publish("execute", { item: nodeWidget.item, node: nodeWidget} );
				// Go tell the model to update the checkbox state

				this.model.updateCheckbox( nodeWidget.item, nodeWidget._checkbox.checked );
				// Generate some additional events
				//this.onClick( nodeWidget.item, nodeWidget, e );
				if(nodeWidget._checkbox.checked) {
					this.onNodeChecked( nodeWidget.item, nodeWidget);
				} else {
					this.onNodeUnchecked( nodeWidget.item, nodeWidget);
				}
				this.focusNode(nodeWidget);
			},

			_onCheckboxChange: function(/*dojo.data.Item*/ storeItem ) {
				// summary:
				//		Processes notification of a change to a checkbox state (triggered by the model).
				// description:
				//		Whenever the model changes the state of a checkbox in the dojo.data.store it will
				//		trigger the 'onCheckboxChange' event allowing the Tree to make the same changes
				//		on the tree Node. There are several conditions why a tree node or checkbox does not
				//		exists:
				//		a) 	The node has not been created yet (the user has not expanded the tree node yet).
				//		b) 	The checkbox may be null if condition (a) exists or no 'checkbox' attribute was
				//			specified for the associated dojo.data.item and the attribute 'checkboxAll' is
				//			set to false.
				// tags:
				//		callback
				var model 	 = this.model,
					identity = model.getIdentity(storeItem),
					nodes 	 = this._itemNodesMap[identity];

				// As of dijit.Tree 1.4 multiple references (parents) are supported, therefore we may have
				// to update multiple nodes which are all associated with the same dojo.data.item.
				if( nodes ) {
					dojo.forEach( nodes, function(node) {
						if( node._checkbox != null ) {
							node._checkbox.attr('checked', this.model.getCheckboxState( storeItem ));
						}
					}, this );
				}
			},

			postCreate: function() {
				// summary:
				//		Handle any specifics related to the tree and model after the instanciation of the Tree.
				// description:
				//		Validate if we have a 'write' store first. Subscribe to the 'onCheckboxChange' event
				//		(triggered by the model) and kickoff the initial checkbox data validation.
				//
				var store = this.model.store;
				if(!store.getFeatures()['dojo.data.api.Write']){
					throw new Error("lib.CheckboxTree: store must support dojo.data.Write");
				}
				this.connect(this.model, "onCheckboxChange", "_onCheckboxChange");
				this.model.validateData( this.model.root, this.model );
				this.inherited(arguments);
			},

			_createTreeNode: function( args ) {
				// summary:
				//		Create a new CheckboxTreeNode instance.
				// description:
				//		Create a new CheckboxTreeNode instance.
				return new lib._CheckBoxTreeNode(args);
			}

		});

});

