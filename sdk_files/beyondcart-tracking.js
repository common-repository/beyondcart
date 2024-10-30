const beyondcart = [];

const beyondcartQueue = {
  elements: [],
  add: function (value) {
    // Добавяме заявката към опашката за Ajax заявки
    this.elements.push(value);
    // Ако има само една заявка в опашката, извикваме функцията за изпращане на заявки
    if (this.elements.length === 1) {
      this.processQueue();
    }
  },
  processQueue: async function () {
    [site_id, session_id, user_id, tracking_click] = beyondcartSettings.get(['site_id', 'session_id', 'user_id', 'tc']);
    let player_id = sessionStorage.getItem("beyondcart_player_id");
    // Ако опашката е празна, излизаме от функцията
    if (this.elements.length === 0) {
      return;
    }
    if (player_id == undefined || player_id == null) {
      player_id = await OneSignal.getUserId().then(function (playerId) {
        if (playerId) {
          sessionStorage.setItem("beyondcart_player_id", playerId);
          return playerId;
        }
        return null;
      }).catch(function (error) {
      });
    }

    // Извличаме следващата заявка от опашката
    let data = this.elements[0];
    data.site_id = site_id;
    data.session_id = session_id;
    data.user_id = user_id;
    data.tc = tracking_click;
    data.player_id = player_id;
    data.os = 'web';
    data.app_version = '1.1.2';

    fetch('https://beyondcart.grind.place/api/t/event', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    }).then((response) => {
      if (!response.ok) {
        throw Error(response.statusText);
      }
      return response;
    })
      .then((response) => response.json())
      .then((response) => {

        // Премахваме заявката от опашката
        this.elements.shift();
        // Извикваме функцията за изпращане на следваща заявка
        this.processQueue();

        if (response.session_id) {
          beyondcartSettings.add('session_id', response.session_id);
        }
      })
      .catch((error) => {
        console.error(error);
      });
  },
};

// Функия за добавление на елемент в масива
beyondcart.push = function (value) {
  // push all arguments to array
  Array.prototype.push.apply(this, arguments);

  beyondcartQueue.add(value);
};

// настройки
const beyondcartSettings = {
  prefix: 'beyondcart_',

  // Функция за взимане на всички beyondcart обекти
  getAll: function () {
    let items_return = [];
    ['site_id', 'session_id', 'user_id', 'tc', 'player_id'].map((item) => items_return[item] = this.getKey(item));
    return items_return;
  },
  get: function (key) {
    if (typeof key == 'string') {
      return this.getKey(key);
    }
    if (Array.isArray(key)) {
      let items_return = [];
      key.map((item) => items_return.push(this.getKey(item)));
      return items_return;
    }
  },
  getKey: function (key) {
    let name = this.prefix + key + "=";
    let ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length).replaceAll('%3B',';');
      }
    }
    return null;
  },

  // Функция за добавяне на нов beyondcart обект
  add: function (key, value, ttl = 0) {
    if (ttl == 0) {
      ttl = 365 * 24 * 60 * 60; // 1 year
    }
    const d = new Date();
    d.setTime(d.getTime() + (ttl * 1000));
    let expires = "expires=" + d.toUTCString();
    document.cookie = this.prefix + key + "=" + value + ";" + expires + ";path=/";
  },

  // Функция за изтриване на съществуващ beyondcart обект 
  remove: function (key) {
    this.add(key, "", -1);
  }
};
