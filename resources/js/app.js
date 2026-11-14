// Favourites live in this browser only (localStorage), never on the server.
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.store('fav', {
        shops: Alpine.$persist([]).as('khaaja-fav-shops'),
        dishes: Alpine.$persist([]).as('khaaja-fav-dishes'), // [{ k: dish_key, i: item id }]

        hasShop(id) {
            return this.shops.includes(id);
        },

        hasDish(key) {
            return this.dishes.some((d) => d.k === key);
        },

        dishKeys() {
            return this.dishes.map((d) => d.k);
        },

        // Each toggle returns [id, on], ready for $wire.favourite(type, ...result).
        toggleShop(id) {
            const on = !this.hasShop(id);
            this.shops = on ? [...this.shops, id].slice(-50) : this.shops.filter((s) => s !== id);

            return [id, on];
        },

        toggleDish(key, itemId) {
            const saved = this.dishes.find((d) => d.k === key);

            if (saved) {
                this.dishes = this.dishes.filter((d) => d.k !== key);

                return [saved.i, false]; // take the heart back from the dish that got it
            }

            this.dishes = [...this.dishes, { k: key, i: itemId }].slice(-50);

            return [itemId, true];
        },
    });
});