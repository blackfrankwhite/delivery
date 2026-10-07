// Keep automated pan/zoom checks off community tile servers.
export async function mockMapTiles(context) {
  await context.route('https://tile.openstreetmap.org/**', (route) =>
    route.fulfill({
      contentType: 'image/svg+xml',
      body: '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#edf2f1"/><path d="M0 64h256M0 128h256M0 192h256M64 0v256M128 0v256M192 0v256" stroke="#d7e2df"/><text x="8" y="18" font-size="10" fill="#83958e">Test map tile</text></svg>',
    }),
  );
}
