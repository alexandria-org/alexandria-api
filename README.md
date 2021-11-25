# Alexandria.org API

## Run with docker
Checkout the code, open a terminal and navigate to the directory. [Guide for windows users](/doc/building-on-windows.md)
1. Build docker image
```
docker build . -t alexandria-api
```
2. Run container
```
docker container run --name alexandria-api -p 8080:80 -p 8081:81 -v ${PWD}:/alexandria-api -it -d alexandria-api
```
3. Attach to container.
```
docker exec -it alexandria-api /bin/bash
```
4. Initialize docker
```
/alexandria-api/scripts/init-docker.sh
```
5. Go to http://127.0.0.1:8080 and start development.
6. Go to http://127.0.0.1:8081 to access and edit database.

7. Run tests:
```
./phpunit --bootstrap functions.php tests

## API Endpoints
### Search
```
https://api.alexandria.org/?q=test%20query&p=1&a=1&c=a
```
Parameters:
```
q: the query
p: the page number 1 to 10
a: anonymous flag, 0 for default behaviour 1 for anonymous search.
c: cluster (a or b)
```
Response:
```
{
   "status":"success",
   "time_ms":535.438060760498,
   "total_found":105245,
   "page_max":10,
   "results":[
      {
         "url":"https:\/\/github.com\/",
         "title":"GitHub",
         "snippet":"...",
         "score":32.5283701133728,
         "domain_hash":"5468486186948880458",
         "url_hash":"5468481278583313044",
         "exact_match":0,
         "phrase_match":2,
         "year":9999,
         "is_old":0,
         "is_subdomain":0,
         "domain":"github.com",
         "display_url":"https:\/\/github.com\/dannote\/recattle"
      }
   ]
}
```

### Query URL
```
https://api.alexandria.org/url?u=http://example.com&c=a
```
Parameters:
```
u: the url to check if it is in the cluster
c: cluster (a or b)
```
Response:
```
{
	"status":"success",
	"result":"...TSV DATA..."
}
```
