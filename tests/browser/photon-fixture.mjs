import http from 'node:http';
const feature = (street, house, lon, lat) => ({
  type: 'Feature',
  geometry: { type: 'Point', coordinates: [lon, lat] },
  properties: {
    street,
    housenumber: house,
    city: 'Tbilisi',
    country: 'Georgia',
  },
});
http
  .createServer((req, res) => {
    const query =
      new URL(req.url, 'http://localhost').searchParams
        .get('q')
        ?.toLowerCase() || '';
    res.setHeader('Content-Type', 'application/json');
    if (query.includes('provider down')) {
      res.statusCode = 503;
      res.end('{}');
      return;
    }
    const features = query.includes('no match')
      ? []
      : query.includes('kazbegi')
        ? [feature('Kazbegi Avenue', '26', 44.7654321, 41.7276543)]
        : [
            feature('Rustaveli Avenue', '12', 44.8271234, 41.7151234),
            feature('Rustaveli Avenue', '24', 44.8298765, 41.7187654),
          ];
    res.end(JSON.stringify({ type: 'FeatureCollection', features }));
  })
  .listen(2323, 'localhost', () =>
    process.stdout.write('Photon test fixture listening on localhost:2323\n'),
  );
