document.addEventListener('DOMContentLoaded', () => {
  const cookie = getCookie('isPopupClosed');
  const popupDesktopElement = document.querySelector('.popup-desktop');
 
   setTimeout(() => {
     if (!cookie && popupDesktopElement) {
         console.log("show the banner");
         document.querySelector('.popup-desktop').style.display = 'block';
         document.querySelector('.popup-app__close').addEventListener('click', () => {
             setCookie('isPopupClosed', true, 4); // set the cookie to expire in 4 hours
             document.querySelector('.popup-app').style.display = 'none';
         });
     }
   }, 4000);
 })
 
 function setCookie(cname, cvalue, hours) {
   const d = new Date();
   d.setTime(d.getTime() + (hours * 60 * 60 * 1000)); // Convert hours to milliseconds
   
   const expires = 'expires='+ d.toUTCString();
   document.cookie = cname + '=' + cvalue + ';' + expires + ';path=/';
 }
 
 function getCookie(cname) {
     const name = cname + '=';
     const decodedCookie = decodeURIComponent(document.cookie);
     const ca = decodedCookie.split(';');
 
     for(let i = 0; i <ca.length; i++) {
         let c = ca[i];
         while (c.charAt(0) == ' ') {
             c = c.substring(1);
         }
 
         if (c.indexOf(name) === 0) {
             return c.substring(name.length, c.length);
         }
     }
 
     return '';
 }