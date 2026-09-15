import '../css/app.css';
import './modules/video-player';
import './modules/swiper-init';
import './modules/lightgallery-init';
import './modules/cart-checkout-bootstrap';
import './modules/product-bootstrap';
import './modules/cities-load-more';
import './modules/products-load-more';
import { initIntlTelInputs } from './modules/intl-tel-input';
import Alpine from 'alpinejs';
import setScrollBarWidth from './modules/set-scrollbar-width';
import { initDeliveryTimers } from './modules/delivery-timer';
import { initMenu } from './modules/menu';
import { initCounterAnimation } from './modules/counter-animation';
import collapse from '@alpinejs/collapse';
import mask from '@alpinejs/mask';

// Register collapse plugin
window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.plugin(mask);

setScrollBarWidth();
initMenu();
initCounterAnimation();
initDeliveryTimers();
initIntlTelInputs();
Alpine.start();
