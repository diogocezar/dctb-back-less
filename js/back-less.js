BackLess = {
	token     : null,
	callBacks : {},
	url       : { 'send_email' : 'http://backless.diogocezar.com.br/send-mail'	},
	data      : [],
	__is_empty: function(val){
		return (val == "" || val == null || val == undefined);
	},
	init : function(){
		BackLess.setSendButton();
	},
	setSendButton: function(){
		$('[data-backless-send]').on('click', function(event){
			event.preventDefault();
			BackLess.sendForm();
		});
	},
	sendForm: function(){
		$(document).find('[data-backless-form]').each(function(){
			var fields = [];
			var values = [];
			$(this).find("input, textarea, select").each(function(){
				fields.push($(this).attr('id'));
				values.push($(this).val());
			});
			BackLess.data = {
				'fields' : fields,
				'values' : values
			};
			$.ajax({
				type        : "POST",
				url         : BackLess.url.send_email,
				crossDomain : true,
				data        : { 'data' : BackLess.data, 'token' : BackLess.token },
				success: function (result){
					json = $.parseJSON(result);
					if(!BackLess.__is_empty(BackLess.callBacks.success) && json.success == "true")
						BackLess.callBacks.success(json);
					else{
						if(!BackLess.__is_empty(BackLess.callBacks.error))
							BackLess.callBacks.error(json);
					}
				}
			});
		});
	}
}

$(document).ready(function() {
	BackLess.init();
});