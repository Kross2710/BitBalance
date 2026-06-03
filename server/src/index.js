// Production entrypoint: import the configured app and bind a port. All app
// wiring lives in app.js so tests can import the app without listening.
import app from './app.js';

const PORT = Number(process.env.PORT || 3000);

app.listen(PORT, () => {
  console.log(`BitBalance API listening on http://localhost:${PORT}`);
});
