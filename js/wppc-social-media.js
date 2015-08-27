(function(d, s, id) {
	var js, fjs = d.getElementsByTagName(s)[0];
	if (d.getElementById(id)) return;
	js = d.createElement(s); js.id = id;
	js.src = "//connect.facebook.net/en_US/all.js";
	fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));

// async init once loading is done
window.fbAsyncInit = function() {
	FB.init({appId: 730165393722202, status: false});
};

// share on facebook callback
function shareOnFacebook(link, picture)
{
	FB.ui({
	    method: 'feed',
	    link: link,
	    picture: picture,
	    name: 'Sharrrre!',
	    caption: 'Awesomesauce',
	    // description: 'Must read daily!'
	  });
}