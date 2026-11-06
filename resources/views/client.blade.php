<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Klien Bank Sampah Digital</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; max-width: 760px; margin: 2rem auto; padding: 0 1rem; color: #17211b; }
        form, section { border: 1px solid #cbd5ce; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
        label { display: block; margin: .6rem 0; }
        input { padding: .5rem; min-width: 220px; }
        button { padding: .5rem .8rem; cursor: pointer; margin: .2rem; }
        pre { white-space: pre-wrap; overflow-wrap: anywhere; background: #f2f5f3; padding: 1rem; border-radius: 6px; }
    </style>
</head>
<body>
    <h1>Bank Sampah Digital</h1>
    <p>Klien demo untuk mencoba login, katalog, saldo, riwayat, dan penukaran poin.</p>
    <form id="login-form">
        <h2>Login nasabah</h2>
        <label>Email <input name="email" type="email" required></label>
        <label>Kata sandi <input name="password" type="password" required></label>
        <button type="submit">Login</button>
        <button type="button" id="logout">Hapus token</button>
    </form>
    <section>
        <h2>Aksi API</h2>
        <button data-action="catalog">Lihat jenis sampah</button>
        <button data-action="me">Profil saya</button>
        <button data-action="balance">Lihat saldo</button>
        <button data-action="transactions">Riwayat transaksi</button>
        <form id="redeem-form">
            <label>ID hadiah <input name="hadiah_id" type="number" min="1" required></label>
            <label>Jumlah <input name="jumlah" type="number" min="1" value="1" required></label>
            <button type="submit">Tukar poin</button>
        </form>
    </section>
    <h2>Respons</h2>
    <pre id="output">Login untuk mulai menggunakan API.</pre>
    <script>
        const api = '/api/v1';
        const output = document.querySelector('#output');
        let token = sessionStorage.getItem('bank-sampah-token');
        let memberId = null;
        const show = value => { output.textContent = JSON.stringify(value, null, 2); };

        async function call(path, options = {}) {
            const headers = { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(token ? { Authorization: `Bearer ${token}` } : {}) };
            const response = await fetch(`${api}${path}`, { ...options, headers });
            const result = await response.json();
            show(result);
            if (!response.ok) throw new Error(result.error?.message || 'Permintaan gagal.');
            return result.data;
        }

        document.querySelector('#login-form').addEventListener('submit', async event => {
            event.preventDefault();
            const form = new FormData(event.currentTarget);
            try {
                const data = await call('/auth/login', { method: 'POST', body: JSON.stringify(Object.fromEntries(form)) });
                token = data.token;
                memberId = data.user.id;
                sessionStorage.setItem('bank-sampah-token', token);
            } catch (error) { show({ error: error.message }); }
        });

        document.querySelector('#logout').addEventListener('click', async () => {
            try { if (token) await call('/auth/logout', { method: 'POST' }); }
            catch (error) { show({ error: error.message }); }
            finally { token = null; memberId = null; sessionStorage.removeItem('bank-sampah-token'); }
        });

        document.querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', async () => {
            try {
                if (button.dataset.action === 'catalog') return await call('/jenis-sampah');
                if (button.dataset.action === 'me') {
                    const user = await call('/auth/me');
                    memberId = user.id;
                    return;
                }
                if (!memberId) {
                    const user = await call('/auth/me');
                    memberId = user.id;
                }
                const endpoint = button.dataset.action === 'balance' ? `/nasabah/${memberId}/saldo` : `/nasabah/${memberId}/transaksi`;
                await call(endpoint);
            } catch (error) { show({ error: error.message }); }
        }));

        document.querySelector('#redeem-form').addEventListener('submit', async event => {
            event.preventDefault();
            const form = Object.fromEntries(new FormData(event.currentTarget));
            form.hadiah_id = Number(form.hadiah_id);
            form.jumlah = Number(form.jumlah);
            try { await call('/penukaran', { method: 'POST', body: JSON.stringify(form) }); }
            catch (error) { show({ error: error.message }); }
        });
    </script>
</body>
</html>
