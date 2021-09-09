# Alexandria.org API

## Run on docker
1. Build docker image
```
docker build . -t alexandria-api
```
2. Run container
```
docker container run --name alexandria-api -p 8080:80 -v $PWD:/alexandria-api -it -d alexandria-api
```
3. Attach to container.
```
docker exec -it alexandria-api /bin/bash
```
4. Initialize docker
```
/alexandria-api/scripts/init-docker.sh
```
