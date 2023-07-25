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
                socket.emit('test-binary', {success: true, bin1: Buffer.from(new Date().toString()), bin2: Buffer.from('1234567890')});
            });
    });

server.listen(port, () => {
    console.log('Server listening at %d...', port);
});