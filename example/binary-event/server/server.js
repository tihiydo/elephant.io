/**
 * This file is part of the Elephant.io package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Wisembly
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

const server = require('http').createServer();
const io = require('socket.io')(server);

const port = 4000;

io.of('/binary-event')
    .on('connection', socket => {
        console.log('Client connected: %s', socket.id);
        socket
            .on('disconnect', () => {
                console.log('Client disconnected: %s', socket.id);
            })
            .on('test-binary', data => {
                console.log('Client send data: %s', data);
                const payload = [];
                const f = function(p) {
                    if (typeof p === 'object') {
                        Object.keys(p).forEach(k => {
                            if (p[k] instanceof Buffer) {
                                payload.push(p[k]);
                            } else if (typeof p[k] === 'object' && p[k].constructor.name === 'Object') {
                                f(p[k]);
                            }
                        });
                    }
                }
                f(data);
                socket.emit('test-binary', {success: true, time: Buffer.from(new Date().toString()), payload: payload});
            });
    });

server.listen(port, () => {
    console.log('Server listening at %d...', port);
});