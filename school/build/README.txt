School ERP styles — how to rebuild school/app.css (only needed if you add new Tailwind classes)

Run from the repository root, on your own computer (not on the server):
  npx tailwindcss@3.4.17 -c school/build/tailwind.config.js -i school/build/app.input.css -o school/app.css --minify

Then commit school/app.css. The server never runs this.
